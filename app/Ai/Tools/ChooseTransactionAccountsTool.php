<?php

namespace App\Ai\Tools;

use App\Ai\Agents\TransactionAccountChoiceAgent;
use App\Domains\Account\Models\Account;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;

class ChooseTransactionAccountsTool extends FinancialTool
{
    public function description(): Stringable|string
    {
        return 'Choose destination account IDs for one or more transaction memo fragments by matching them against accounts accessible to the authenticated user. Use this before create_transaction when you already know the source account but need destination-account inference from memo text. This tool never chooses the source account.';
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            $user = $this->authenticatedUser();
            $memos = $this->validatedMemos($request->all());

            $candidateAccounts = Account::query()
                ->accessibleTo($user)
                ->orderByRaw(Account::localizedNameSqlExpression(app()->getLocale()) . ' ASC')
                ->get()
                ->filter(fn (Account $account): bool => $account->canBeEditedBy($user))
                ->values()
                ->map(fn (Account $account): array => [
                    'id' => (int) $account->id,
                    'name' => $account->getLocalizedName() ?? 'Unnamed account',
                    'type' => $account->type ?? Account::TYPE_ASSET,
                ])
                ->values()
                ->all();

            if ($candidateAccounts === []) {
                return $this->encodeMatches($this->emptyMatches($memos));
            }

            $response = (new TransactionAccountChoiceAgent())->prompt(
                $this->buildPrompt($memos, $candidateAccounts)
            );

            return $this->encodeMatches(
                $this->normalizeMatches(
                    $memos,
                    is_array($response['matches'] ?? null) ? $response['matches'] : [],
                    array_map(static fn (array $account): int => (int) $account['id'], $candidateAccounts),
                )
            );
        } catch (RuntimeException $exception) {
            return $this->recoverableToolFailure(
                'choose destination accounts for the transaction memos',
                $exception,
                'Pass a memos array with one or more memo fragments, then retry choose_transaction_accounts.',
            );
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'memos' => $schema->array()
                ->items($schema->string()->description('A memo fragment for a pending transaction, such as a merchant name or spending note.'))
                ->description('One or more memo fragments that need destination-account matching.')
                ->min(1)
                ->required(),
        ];
    }

    private function validatedMemos(array $input): array
    {
        $validated = $this->validateInput($input, [
            'memos' => ['required', 'array', 'min:1'],
        ]);

        $memos = array_map(function (mixed $memo): ?string {
            $normalized = $this->normalizeOptionalTextValue($memo);

            return $normalized === null ? null : mb_substr($normalized, 0, 255);
        }, $validated['memos']);

        if (in_array(null, $memos, true)) {
            throw new RuntimeException('Each memo must be a non-empty text fragment.');
        }

        $revalidated = $this->validateInput(['memos' => $memos], [
            'memos' => ['required', 'array', 'min:1'],
            'memos.*' => ['required', 'string', 'max:255'],
        ]);

        return array_values($revalidated['memos']);
    }

    private function buildPrompt(array $memos, array $candidateAccounts): string
    {
        $payload = json_encode([
            'memos' => $memos,
            'candidate_accounts' => $candidateAccounts,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
Match each memo to the best destination account from the provided candidates.
Return structured output only.
Source-account selection is handled separately and must not be inferred here.

Payload:
{$payload}
PROMPT;
    }

    private function normalizeMatches(array $memos, array $rawMatches, array $candidateAccountIds): array
    {
        $matchesByMemo = [];

        foreach ($rawMatches as $rawMatch) {
            if (! is_array($rawMatch)) {
                continue;
            }

            $memo = $this->normalizeOptionalTextValue($rawMatch['memo'] ?? null);

            if ($memo === null) {
                continue;
            }

            $accountId = $rawMatch['account_id'] ?? null;

            if (is_string($accountId) && ctype_digit(trim($accountId))) {
                $accountId = (int) trim($accountId);
            }

            if (! is_int($accountId) || ! in_array($accountId, $candidateAccountIds, true)) {
                $accountId = null;
            }

            $matchesByMemo[$memo] ??= [];
            $matchesByMemo[$memo][] = [
                'memo' => $memo,
                'account_id' => $accountId,
            ];
        }

        $normalized = [];

        foreach ($memos as $memo) {
            $memoMatches = $matchesByMemo[$memo] ?? null;

            $normalized[] = is_array($memoMatches) && $memoMatches !== []
                ? array_shift($matchesByMemo[$memo])
                : [
                'memo' => $memo,
                'account_id' => null,
            ];
        }

        return $normalized;
    }

    private function emptyMatches(array $memos): array
    {
        return array_map(static fn (string $memo): array => [
            'memo' => $memo,
            'account_id' => null,
        ], $memos);
    }

    private function encodeMatches(array $matches): string
    {
        return json_encode([
            'matches' => $matches,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{"matches": []}';
    }
}