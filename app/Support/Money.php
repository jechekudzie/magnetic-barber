<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Money is always an integer of minor units plus a currency. Never a float,
 * and never a bare integer without the currency travelling alongside it.
 */
final readonly class Money implements \JsonSerializable
{
    public function __construct(
        public int $cents,
        public string $currency = 'USD',
    ) {}

    public static function of(float|int|string $amount, string $currency = 'USD'): self
    {
        return new self((int) round(((float) $amount) * 100), strtoupper($currency));
    }

    public static function ofCents(?int $cents, ?string $currency = 'USD'): self
    {
        return new self($cents ?? 0, strtoupper($currency ?? 'USD'));
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->cents + $other->cents, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->cents - $other->cents, $this->currency);
    }

    public function times(int $multiplier): self
    {
        return new self($this->cents * $multiplier, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function amount(): float
    {
        return $this->cents / 100;
    }

    public function symbol(): string
    {
        return match ($this->currency) {
            'USD' => '$',
            'ZWG' => 'ZiG ',
            default => $this->currency.' ',
        };
    }

    /**
     * Whole amounts read better on a price list without the trailing zeros.
     */
    public function format(): string
    {
        $decimals = $this->cents % 100 === 0 ? 0 : 2;

        return $this->symbol().number_format($this->amount(), $decimals);
    }

    /**
     * The shape both the website and the mobile app consume, so neither has to
     * know how to turn cents into a string.
     *
     * @return array{cents: int, currency: string, amount: float, formatted: string}
     */
    public function toArray(): array
    {
        return [
            'cents' => $this->cents,
            'currency' => $this->currency,
            'amount' => $this->amount(),
            'formatted' => $this->format(),
        ];
    }

    /**
     * @return array{cents: int, currency: string, amount: float, formatted: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return $this->format();
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Cannot combine {$this->currency} with {$other->currency}."
            );
        }
    }
}
