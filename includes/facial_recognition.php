<?php

/**
 * Comparacao segura dos descritores de 128 dimensoes produzidos pelo face-api.js.
 * O modelo e treinado para distancia euclidiana: menor distancia significa maior
 * semelhanca. Similaridade de cosseno nao deve ser usada como limiar de identidade.
 */

const FACIAL_DESCRIPTOR_SIZE = 128;
const FACIAL_MAX_DISTANCE = 0.55;
const FACIAL_MIN_SEPARATION = 0.04;

function facialNormalizeDescriptor($descriptor): ?array
{
    if (!is_array($descriptor) || count($descriptor) !== FACIAL_DESCRIPTOR_SIZE) {
        return null;
    }

    $normalized = [];
    foreach ($descriptor as $value) {
        if (!is_numeric($value)) {
            return null;
        }

        $number = (float) $value;
        if (!is_finite($number) || abs($number) > 10) {
            return null;
        }
        $normalized[] = $number;
    }

    return $normalized;
}

function facialEuclideanDistance($descriptorA, $descriptorB): ?float
{
    $a = facialNormalizeDescriptor($descriptorA);
    $b = facialNormalizeDescriptor($descriptorB);
    if ($a === null || $b === null) {
        return null;
    }

    $sum = 0.0;
    for ($i = 0; $i < FACIAL_DESCRIPTOR_SIZE; $i++) {
        $delta = $a[$i] - $b[$i];
        $sum += $delta * $delta;
    }

    return sqrt($sum);
}

function facialBestMatch(array $probe, array $faces): array
{
    if (facialNormalizeDescriptor($probe) === null) {
        return ['matched' => false, 'reason' => 'invalid_descriptor'];
    }

    $candidates = [];
    foreach ($faces as $face) {
        $samples = json_decode((string) ($face['descritores'] ?? ''), true);
        if (!is_array($samples)) {
            continue;
        }

        // Compatibilidade com cadastros antigos que guardaram uma amostra sem envelope.
        if (count($samples) === FACIAL_DESCRIPTOR_SIZE && !is_array(reset($samples))) {
            $samples = [$samples];
        }

        $bestForPerson = INF;
        foreach ($samples as $sample) {
            $distance = facialEuclideanDistance($probe, $sample);
            if ($distance !== null && $distance < $bestForPerson) {
                $bestForPerson = $distance;
            }
        }

        if (is_finite($bestForPerson)) {
            $candidates[] = ['face' => $face, 'distance' => $bestForPerson];
        }
    }

    usort($candidates, static fn(array $left, array $right): int => $left['distance'] <=> $right['distance']);
    if (!$candidates || $candidates[0]['distance'] > FACIAL_MAX_DISTANCE) {
        return [
            'matched' => false,
            'reason' => 'distance',
            'distance' => $candidates[0]['distance'] ?? null,
        ];
    }

    $runnerUpDistance = $candidates[1]['distance'] ?? null;
    if ($runnerUpDistance !== null && ($runnerUpDistance - $candidates[0]['distance']) < FACIAL_MIN_SEPARATION) {
        return [
            'matched' => false,
            'reason' => 'ambiguous',
            'distance' => $candidates[0]['distance'],
            'runner_up_distance' => $runnerUpDistance,
        ];
    }

    return [
        'matched' => true,
        'face' => $candidates[0]['face'],
        'distance' => $candidates[0]['distance'],
        'runner_up_distance' => $runnerUpDistance,
    ];
}

