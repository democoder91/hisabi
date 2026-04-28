<?php

namespace Tests\Unit\Ai\Agents;

use App\Ai\Agents\TransactionAccountChoiceAgent;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Tests\TestCase;

class TransactionAccountChoiceAgentTest extends TestCase
{
    public function test_it_describes_the_destination_account_selection_constraints(): void
    {
        $instructions = (string) (new TransactionAccountChoiceAgent())->instructions();

        $this->assertStringContainsString('Choose only destination accounts from the provided candidates.', $instructions);
        $this->assertStringContainsString('If confidence is low, the memo is ambiguous, or no candidate clearly fits, return account_id as null.', $instructions);
        $this->assertStringContainsString('Never infer, select, or explain the source account.', $instructions);
    }

    public function test_it_exposes_a_matches_schema_with_nullable_account_ids(): void
    {
        $schema = (new TransactionAccountChoiceAgent())->schema(new JsonSchemaTypeFactory());

        $this->assertArrayHasKey('matches', $schema);

        $serializedSchema = $schema['matches']->toString();

        $this->assertStringContainsString('"type": "array"', $serializedSchema);
        $this->assertStringContainsString('"memo"', $serializedSchema);
        $this->assertStringContainsString('"account_id"', $serializedSchema);
        $this->assertStringContainsString('"null"', $serializedSchema);
    }
}