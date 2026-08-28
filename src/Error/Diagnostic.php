<?php

/**
 * One structured diagnostic, common.schema.json#/$defs/diagnostic.
 *
 * Diagnostics are the only channel a refusal has for pointing at data: a
 * bounded code, severity, message reference, location, scalar parameters,
 * and remediation keys. Parameters carry structural facts (a member name,
 * a bound, a count) — never resource values or host internals.
 *
 * @since 0.1.0
 */

declare(strict_types=1);

namespace Kumwe\Producer\Error;

final class Diagnostic
{
    public const SEVERITIES = ['information', 'warning', 'error', 'blocking'];

    /**
     * @param array<string, string|int|float|bool|null> $parameters At most 20,
     *                                                              safe member names, scalar or null values.
     * @param list<string>                              $remediations At most 10 qualified names.
     */
    public function __construct(
        private readonly string $code,
        private readonly string $severity,
        private readonly MessageReference $message,
        private readonly ?DiagnosticLocation $location = null,
        private readonly array $parameters = [],
        private readonly array $remediations = [],
    ) {
        if (!ContractGrammar::isQualifiedName($code)) {
            throw new \InvalidArgumentException('A diagnostic code must be a contract qualified name.');
        }
        if (!in_array($severity, self::SEVERITIES, true)) {
            throw new \InvalidArgumentException('A diagnostic severity must be one of the four contract severities.');
        }
        if (count($parameters) > 20) {
            throw new \InvalidArgumentException('A diagnostic carries at most 20 parameters.');
        }
        foreach ($parameters as $name => $value) {
            if (!ContractGrammar::isSafeJsonMemberName((string) $name)) {
                throw new \InvalidArgumentException('Every diagnostic parameter name must be a safe JSON member name.');
            }
            if (is_string($value)) {
                if (!mb_check_encoding($value, 'UTF-8')) {
                    throw new \InvalidArgumentException('A string diagnostic parameter must be valid UTF-8.');
                }
                continue;
            }
            if (is_float($value) && !is_finite($value)) {
                throw new \InvalidArgumentException('A numeric diagnostic parameter must be finite.');
            }
            if (!is_int($value) && !is_float($value) && !is_bool($value) && $value !== null) {
                throw new \InvalidArgumentException('A diagnostic parameter must be a string, number, boolean, or null.');
            }
        }
        if (!array_is_list($remediations) || count($remediations) > 10) {
            throw new \InvalidArgumentException('Diagnostic remediations must be a list of at most 10 entries.');
        }
        foreach ($remediations as $remediation) {
            if (!is_string($remediation) || !ContractGrammar::isQualifiedName($remediation)) {
                throw new \InvalidArgumentException('Every remediation must be a contract qualified name.');
            }
        }
    }

    public function code(): string
    {
        return $this->code;
    }

    public function severity(): string
    {
        return $this->severity;
    }

    public function message(): MessageReference
    {
        return $this->message;
    }

    public function location(): ?DiagnosticLocation
    {
        return $this->location;
    }

    /**
     * @return array<string, string|int|float|bool|null>
     */
    public function parameters(): array
    {
        return $this->parameters;
    }

    /**
     * @return list<string>
     */
    public function remediations(): array
    {
        return $this->remediations;
    }

    public function toDocument(): \stdClass
    {
        $document = new \stdClass();
        $document->code = $this->code;
        $document->severity = $this->severity;
        $document->message = $this->message->toDocument();
        if ($this->location !== null) {
            $document->location = $this->location->toDocument();
        }
        if ($this->parameters !== []) {
            $parameters = new \stdClass();
            foreach ($this->parameters as $name => $value) {
                $parameters->{(string) $name} = $value;
            }
            $document->parameters = $parameters;
        }
        if ($this->remediations !== []) {
            $document->remediations = $this->remediations;
        }

        return $document;
    }
}
