<?php

namespace Phutilities;

class Validator
{
    private array $schema;
    private bool $isThrowable;

    public function __construct(array $schema, bool $isThrowable = false)
    {
        $this->schema = $schema;
        $this->isThrowable = $isThrowable;
    }

    public function validate(mixed $value)
    {
        return $this->handleValidate($value, [], $this->schema);
    }

    private function handleValidate(mixed $test, array $level, array $schema)
    {
        if (empty($schema)) {
            $textSchema = $this->format($schema);
            throw new \Exception(
                "Validator: $textSchema bad structured (array cannot be empty)"
            );
        }

        $is_numeric = null;
        array_map(function ($key) use (&$is_numeric, $schema) {
            if (is_null($is_numeric)) {
                $is_numeric = is_numeric($key);
            } else {
                if ($is_numeric !== is_numeric($key)) {
                    $textSchema = $this->format($schema);
                    throw new \Exception(
                        "Validator: $textSchema bad structured (array can contain only values ​​or keys and values)"
                    );
                }
            }
        }, array_keys($schema));

        if ($is_numeric)
            return $this->validateOnlyValues($test, $level, $schema);
        return $this->validateKeysAndValues($test, $level, $schema);
    }

    private function validateOnlyValues(mixed $test, array $level, array $schema)
    {
        $errors = [];
        foreach ($schema as $subSchema) {
            if (is_array($subSchema)) {
                try {
                    $result = $this->handleValidate($test, $level, $subSchema);
                } catch (\Throwable $th) {
                    if ($th->getCode() == -1)
                        throw $th;
                    $result = false;
                    $errors[] = $th->getMessage();
                }
                if ($result)
                    return true;
            }

            if (in_array($test, $schema))
                return true;
        }
        $path = implode(" => ", [...$level, $this->format($test)]);
        $textSchema = $this->format($schema);
        if ($this->isThrowable) {
            $textErrors = empty($errors) ? "" : " ( \n" . implode("; \n", $errors) . "\n )";
            throw new \Exception(
                "Validator: value [ $path ]  not is valid in schema $textSchema" . $textErrors
            );
        } else
            return false;
    }

    private function validateKeysAndValues(mixed $test, array $level, array $schema)
    {
        if (!is_array($test))
            if ($this->isThrowable) {
                $path = implode(" => ", $level);
                throw new \Exception(
                    "Validator: value of [ $path ]  not is a array"
                );
            } else
                return false;

        foreach ($schema as $keySubSchema => $subSchema) {
            if (is_array($subSchema)) {
                if (!$this->handleValidate($test[$keySubSchema], [...$level, $keySubSchema], $subSchema))
                    return false;
                continue;
            }

            try {
                $resultValidation = call_user_func("is_" . $subSchema, $test[$keySubSchema]);
            } catch (\Throwable) {
                $textSchema = $this->format($schema);
                throw new \Exception(
                    "Validator: $textSchema bad structured ($subSchema is an unsupported validation type)",
                    -1
                );
            }
            if ($resultValidation == false) {
                if ($this->isThrowable) {
                    $path = implode(" => ", [...$level, $keySubSchema]);
                    throw new \Exception(
                        "Validator: value '$path' not is a $subSchema"
                    );
                } else
                    return false;
            }
        }
        return true;
    }

    public function format(mixed $schema)
    {
        if (!is_array($schema))
            return "$schema";
        $textSchemaBase = array_map(function ($key, $value) {
            if (is_array($value))
                $value = $this->format($value);
            if (is_numeric($key))
                return "$value";
            return "{$key} => $value";
        }, array_keys($schema), $schema);
        return "[ " . implode(", ", $textSchemaBase) . " ]";
    }
}
