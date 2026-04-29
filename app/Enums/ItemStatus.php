<?php

namespace App\Enums;

enum ItemStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Listed = 'listed';
    case Reserved = 'reserved';
    case Sold = 'sold';
    case Withdrawn = 'withdrawn';

    /**
     * Allowed transitions, keyed by source status.
     *
     * @return array<string, list<self>>
     */
    public static function transitionMap(): array
    {
        return [
            self::Draft->value => [self::Ready, self::Withdrawn],
            self::Ready->value => [self::Listed, self::Withdrawn],
            self::Listed->value => [self::Reserved, self::Sold, self::Withdrawn],
            self::Reserved->value => [self::Listed, self::Sold, self::Withdrawn],
            self::Sold->value => [self::Withdrawn],
            self::Withdrawn->value => [self::Draft],
        ];
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, self::transitionMap()[$this->value] ?? [], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Ready => 'Ready',
            self::Listed => 'Listed',
            self::Reserved => 'Reserved',
            self::Sold => 'Sold',
            self::Withdrawn => 'Withdrawn',
        };
    }
}
