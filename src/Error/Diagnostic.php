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

/**
 * One structured diagnostic attached to a refusal: a stable code, one of
 * the four contract severities, a non-disclosing message, and bounded
 * structured context.
 *
 * Every member is proven against the contract grammars and bounds at
 * construction, so an instance that exists is a valid diagnostic document.
 * Parameters are the sanctioned channel for structural facts — a member
 * name, a bound, a count — and are capped at 20 scalar-or-null entries
 * under safe member names; they must never carry resource values or host
 * internals.
 *
 * @since   0.1.0
 */
final class Diagnostic
{
    /**
     * The four contract severities, mildest first: information, warning,
     * error, and blocking.
     *
     * @since   0.1.0
     */
    public const SEVERITIES = ['information', 'warning', 'error', 'blocking'];

    /**
     * Proves every member against the contract grammars and bounds; a
     * violation is refused at construction, never emitted on the wire.
     *
     * @param   string                                     $code          The stable diagnostic
     *                                                                    code — a contract
     *                                                                    qualified name.
     * @param   string                                     $severity      One of the four contract
     *                                                                    severities.
     * @param   MessageReference                           $message       The non-disclosing
     *                                                                    human-facing message.
     * @param   DiagnosticLocation|null                    $location      Where the diagnostic
     *                                                                    points, or null.
     * @param   array<string, string|int|float|bool|null>  $parameters    At most 20,
     *                                                                    safe member names, scalar or null values.
     * @param   list<string>                               $remediations  At most 10 qualified names.
     *
     * @throws  \InvalidArgumentException  When any member breaks its
     *                                     grammar or bound.
     *
     * @since   0.1.0
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

    /**
     * The stable diagnostic code callers may match on.
     *
     * @return  string  A contract qualified name.
     *
     * @since   0.1.0
     */
    public function code(): string
    {
        return $this->code;
    }

    /**
     * The diagnostic's severity.
     *
     * @return  string  One of the four contract severities.
     *
     * @since   0.1.0
     */
    public function severity(): string
    {
        return $this->severity;
    }

    /**
     * The non-disclosing human-facing message.
     *
     * @return  MessageReference  A catalog key plus optional fallback.
     *
     * @since   0.1.0
     */
    public function message(): MessageReference
    {
        return $this->message;
    }

    /**
     * Where the diagnostic points, when it points anywhere.
     *
     * @return  DiagnosticLocation|null  The location, or null.
     *
     * @since   0.1.0
     */
    public function location(): ?DiagnosticLocation
    {
        return $this->location;
    }

    /**
     * The structural facts attached to the diagnostic.
     *
     * @return  array<string, string|int|float|bool|null>  At most 20
     *                                                     entries under
     *                                                     safe member names.
     *
     * @since   0.1.0
     */
    public function parameters(): array
    {
        return $this->parameters;
    }

    /**
     * The remediation actions a surface may offer for this diagnostic.
     *
     * @return  list<string>  At most 10 contract qualified names.
     *
     * @since   0.1.0
     */
    public function remediations(): array
    {
        return $this->remediations;
    }

    /**
     * The schema-shaped diagnostic document; empty optional members are
     * omitted, never emitted as null or empty collections.
     *
     * @return  \stdClass  The document ready for canonical serialization.
     *
     * @since   0.1.0
     */
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
