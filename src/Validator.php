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
            try {
                if (is_array($subSchema)) {
                    $result = $this->handleValidate($test, $level, $subSchema);

                    if ($result)
                        return true;
                    continue;
                }

                try {
                    if ($this->validateValueOrType($test, $level, $subSchema))
                        return true;
                } catch (\Throwable) {
                    $textSchema = $this->format($schema);
                    throw new \Exception(
                        "Validator: $textSchema bad structured ($subSchema is an unsupported validation type)",
                        -1
                    );
                }
            } catch (\Throwable $th) {
                if ($th->getCode() == -1)
                    throw $th;
                if ($this->isThrowable)
                    $errors[] = $th->getMessage();
                $result = false;
            }
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
            if (!key_exists($keySubSchema, $test)) {
                if ($this->isThrowable) {
                    $path = implode(" => ", [...$level]);
                    throw new \Exception(
                        "Validator: the key '$keySubSchema' not exist in [ $path ]"
                    );
                } else
                    return false;
            }

            if (is_array($subSchema)) {
                if ($this->handleValidate($test[$keySubSchema], [...$level, $keySubSchema], $subSchema))
                    continue;
                return false;
            }

            try {
                if ($this->validateValueOrType($test[$keySubSchema], [...$level, $keySubSchema], $subSchema))
                    continue;
            } catch (\Throwable $th) {
                $textSchema = $this->format($schema);
                if ($th->getCode() == -1)
                    throw $th;
                throw new \Exception(
                    "Validator: $textSchema bad structured ($subSchema is an unsupported validation type)",
                    -1
                );
            }

            if ($this->isThrowable) {
                $path = implode(" => ", [...$level, $keySubSchema]);
                throw new \Exception(
                    "Validator: value [ $path ] not is a $subSchema"
                );
            } else
                return false;
        }
        return true;
    }

    public function validateValueOrType(mixed $test, array $level, string $subSchema)
    {
        if (substr($subSchema, 0, 1) == ":") {
            if (Text::removeFromStart($test, ":") == $subSchema)
                return true;
        } else {
            if ($this->validType($subSchema, $test))
                return true;
            if ($this->isThrowable) {
                $path = implode(" => ", [...$level, $this->format($test)]);
                $textSchema = $this->format($subSchema);
                throw new \Exception(
                    "Validator: value [ $path ]  not is valid in schema $textSchema",
                );
            } else
                return false;
        }
    }

    public function validType(mixed $schema, $value)
    {
        $types = explode("|", $schema);
        $resultValidation = false;
        foreach ($types as $type) {
            $resultValidation = $resultValidation || call_user_func("is_" . $type, $value);
            if ($resultValidation) break;
        }
        return $resultValidation;
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
