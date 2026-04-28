<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider('openai')]
class TransactionAccountChoiceAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
You choose destination accounts for transaction memos.

You will receive memo fragments and a list of candidate accounts that are already accessible to the current user.

Rules:
- Choose only destination accounts from the provided candidates.
- Use candidate account names and ledger types as matching hints.
- Return one result for each provided memo.
- Preserve each memo exactly as provided.
- Only return an account_id when there is a confident single match.
- If confidence is low, the memo is ambiguous, or no candidate clearly fits, return account_id as null.
- Never infer, select, or explain the source account.
- Never create accounts, modify data, or use IDs that were not provided in the candidate list.
PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'matches' => $schema->array()
                ->items($schema->object([
                    'memo' => $schema->string()
                        ->description('The original memo fragment exactly as provided in the prompt.')
                        ->required(),
                    'account_id' => $schema->integer()
                        ->description('The chosen destination account ID from the provided candidates, or null when there is no confident match.')
                        ->required()
                        ->nullable(),
                ])->withoutAdditionalProperties())
                ->description('One normalized destination-account choice per memo.')
                ->required(),
        ];
    }
}