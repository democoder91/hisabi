<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('agent_conversation_messages')
            ->where('role', 'assistant')
            ->whereNotNull('tool_calls')
            ->where('tool_calls', '!=', '[]')
            ->orderBy('id')
            ->each(function (object $row): void {
                $toolCalls = json_decode($row->tool_calls, true);

                if (! is_array($toolCalls)) {
                    return;
                }

                $dirty = false;

                $toolCalls = array_map(function (array $tc) use (&$dirty): array {
                    if (! isset($tc['result_id']) && isset($tc['id'])) {
                        $tc['result_id'] = $tc['id'];
                        $dirty = true;
                    }

                    return $tc;
                }, $toolCalls);

                $toolResults = json_decode($row->tool_results ?? '[]', true);

                if (is_array($toolResults)) {
                    $toolResults = array_map(function (array $tr) use (&$dirty): array {
                        if (! isset($tr['result_id']) && isset($tr['id'])) {
                            $tr['result_id'] = $tr['id'];
                            $dirty = true;
                        }

                        return $tr;
                    }, $toolResults);
                }

                if ($dirty) {
                    DB::table('agent_conversation_messages')
                        ->where('id', $row->id)
                        ->update([
                            'tool_calls' => json_encode($toolCalls),
                            'tool_results' => json_encode($toolResults ?: []),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Irreversible data backfill — result_id was absent before this migration.
    }
};
