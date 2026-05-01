<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class VectorEmbeddingService
{
    /**
     * @return array<int, float>
     */
    public function generate(string $text): array
    {
        $response = Http::timeout(30)
            ->acceptJson()
            ->post('http://localhost:11434/api/embeddings', [
                'model' => 'nomic-embed-text',
                'prompt' => $text,
            ])
            ->throw();

        $embedding = $response->json('embedding');

        if (! is_array($embedding) || $embedding === []) {
            throw new RuntimeException('Ollama did not return a valid embedding array.');
        }

        $vector = array_map(static fn (mixed $value): float => (float) $value, $embedding);

        if (count($vector) !== 768) {
            throw new RuntimeException('Expected a 768-dimensional embedding from Ollama.');
        }

        return $vector;
    }
}
