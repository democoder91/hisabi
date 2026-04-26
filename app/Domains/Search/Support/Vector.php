<?php

namespace App\Domains\Search\Support;

class Vector
{
    /**
     * Normalize a vector to unit length so dot product equals cosine similarity.
     *
     * @param  array<int, float|int>  $vector
     * @return array<int, float>
     */
    public static function normalize(array $vector): array
    {
        $magnitude = 0.0;

        foreach ($vector as $value) {
            $magnitude += ((float) $value) * ((float) $value);
        }

        $magnitude = sqrt($magnitude);

        if ($magnitude <= 0.0) {
            return array_map(static fn (mixed $value): float => (float) $value, $vector);
        }

        return array_map(static fn (mixed $value) => ((float) $value) / $magnitude, $vector);
    }

    /**
     * Cosine similarity between two vectors. Both should have the same dimensions.
     *
     * @param  array<int, float|int>  $a
     * @param  array<int, float|int>  $b
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        $length = min(count($a), count($b));

        if ($length === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $aMagnitude = 0.0;
        $bMagnitude = 0.0;

        for ($i = 0; $i < $length; $i++) {
            $aValue = (float) $a[$i];
            $bValue = (float) $b[$i];

            $dot += $aValue * $bValue;
            $aMagnitude += $aValue * $aValue;
            $bMagnitude += $bValue * $bValue;
        }

        $denominator = sqrt($aMagnitude) * sqrt($bMagnitude);

        if ($denominator <= 0.0) {
            return 0.0;
        }

        return $dot / $denominator;
    }
}
