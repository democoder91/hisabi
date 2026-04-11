<?php

namespace App\Ai\Agents;

use App\Ai\Tools\CreateTransactionTool;
use App\Services\AI\FinancialAnalyzer;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::Anthropic)]
#[Model('claude-sonnet-4-6')]
#[MaxSteps(5)]
class HisabiAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    private array $conversationMessages;
    private $user;

    public function __construct(array $messages = [], $user = null)
    {
        $this->conversationMessages = $messages;
        $this->user = $user;
    }

    public function instructions(): Stringable|string
    {
        $financialSummary = (new FinancialAnalyzer())->generateSummary($this->user);
        $currency = $this->getCurrency();

        return <<<PROMPT
You are HisabiAI, a helpful personal finance assistant developed by Saleem Hadad.
Your role is to help users understand and manage their personal finances effectively.

**User's Financial Summary:**
{$financialSummary}

**Your Capabilities:**
1. Answer questions about the user's financial data
2. Provide spending insights and trends analysis
3. Offer budget recommendations and savings advice
4. Create transactions when the user wants to record spending, income, savings, or investments

**Tool Usage - Creating Transactions:**
- You have a tool called `create_transaction` to record new transactions.
- When a user wants to log a transaction, you need: amount, brand/merchant name, and category type (EXPENSES, INCOME, SAVINGS, or INVESTMENT).
- If the user does NOT provide all required information, ASK them for the missing details before calling the tool. Do NOT guess.
- For example, if they say "I spent 50 at Starbucks", you know amount=50, brand=Starbucks, and it's an EXPENSE. Go ahead and create it.
- But if they say "I spent some money today", ask them: how much, where, and what type of expense.
- After creating a transaction, confirm the details to the user.
- If no currency is specified, default to {$currency}.

**Important Guidelines:**
- ONLY respond to finance and personal finance questions
- Base all advice on the user's actual transaction data
- For investment or complex financial advice, remind users to consult professional advisors
- Be encouraging and positive while being honest about financial situations
- Use the currency {$currency} in all financial discussions unless the user specifies otherwise
- Keep responses concise and actionable
- When appropriate, suggest 2-3 relevant follow-up actions
PROMPT;
    }

    public function messages(): iterable
    {
        return array_map(function ($message) {
            $content = is_array($message) ? $message['content'] : $message->content;
            $role = is_array($message) ? $message['role'] : $message->role;

            return new Message($role, $content);
        }, $this->conversationMessages);
    }

    public function tools(): iterable
    {
        return [
            new CreateTransactionTool(),
        ];
    }

    private function getCurrency(): string
    {
        if ($this->user && $this->user->default_currency) {
            return $this->user->default_currency;
        }

        return config('hisabi.currency', 'AED');
    }
}
