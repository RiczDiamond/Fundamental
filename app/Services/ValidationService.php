<?php

namespace App\Services;

class ValidationService
{
    public function validateRequired(array $data, array $requiredLabels)
    {
        $errors = [];
        foreach ($requiredLabels as $field => $label) {
            $value = trim((string)($data[$field] ?? ''));
            if ($value === '') {
                $errors[] = $label . ' is verplicht.';
            }
        }
        return $errors;
    }

    public function validateLength($value, $label, $max)
    {
        $value = (string)$value;
        if (mb_strlen($value) > (int)$max) {
            return $label . ' mag maximaal ' . (int)$max . ' tekens bevatten.';
        }
        return '';
    }
}
