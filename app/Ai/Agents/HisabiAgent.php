<?php

namespace App\Ai\Agents;

use App\Ai\Tools\CreateAccountTool;
use App\Ai\Tools\CreateBudgetTool;
use App\Ai\Tools\CreateCategoryTool;
use App\Ai\Tools\CreateTransactionTool;
use App\Ai\Tools\EditAccountTool;
use App\Ai\Tools\EditBudgetTool;
use App\Ai\Tools\EditCategoryTool;
use App\Ai\Tools\EditTransactionTool;
use App\Ai\Tools\ListAccountsTool;
use App\Ai\Tools\ListBudgetsTool;
use App\Ai\Tools\ListCategoriesTool;
use App\Ai\Tools\ListTransactionsTool;
use App\Models\User;
use App\Services\AI\FinancialAnalyzer;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider('zai')]
#[Model('glm-4.7')]
#[MaxSteps(5)]
class HisabiAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    private ?User $user;

    public function __construct(?User $user = null)
    {
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
4. Create, edit, and list accounts
5. Create, edit, and list transactions
6. Create, edit, and list budgets
7. Create, edit, and list categories

**Tool Usage - Accounts, Transactions, Budgets, Categories:**
- Use `list_accounts`, `list_transactions`, `list_budgets`, and `list_categories` whenever you need IDs or the user asks what exists.
- Use `create_account`, `edit_account`, `list_accounts` for account management.
- Use `create_transaction`, `edit_transaction`, `list_transactions` for transaction management.
- Use `create_budget`, `edit_budget`, `list_budgets` for budget management.
- Use `create_category`, `edit_category`, `list_categories` for category management.
- Never guess IDs. If the user asks to edit something and you do not already know the correct ID, list the relevant records first or ask a clarifying question.
- When a create or edit tool requires missing information, ask the user for the missing details before calling the tool.
- For transaction creation, you need the amount and either category_id or category_type. account_id is optional and defaults to the user's default account.
- For budget creation, you need a name, amount, start date, recurrence, period, and at least one category ID.
- For category creation, you need a name, type, color, and icon.
- For account creation, you need a name and starting balance.
- After using a write tool, confirm the result clearly to the user.
- If no currency is specified for a transaction, default to {$currency}.

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

    public function tools(): iterable
    {
        return [
            new CreateAccountTool(),
            new CreateTransactionTool(),
            new CreateBudgetTool(),
            new CreateCategoryTool(),
            new EditAccountTool(),
            new EditTransactionTool(),
            new EditBudgetTool(),
            new EditCategoryTool(),
            new ListAccountsTool(),
            new ListTransactionsTool(),
            new ListBudgetsTool(),
            new ListCategoriesTool(),
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
