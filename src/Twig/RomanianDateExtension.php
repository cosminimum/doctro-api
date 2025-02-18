<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class RomanianDateExtension extends AbstractExtension
{
    private const ROMANIAN_DAYS = [
        'Monday' => 'Luni',
        'Tuesday' => 'Marți',
        'Wednesday' => 'Miercuri',
        'Thursday' => 'Joi',
        'Friday' => 'Vineri',
        'Saturday' => 'Sâmbătă',
        'Sunday' => 'Duminică'
    ];

    private const ROMANIAN_MONTHS = [
        'January' => 'Ianuarie',
        'February' => 'Februarie',
        'March' => 'Martie',
        'April' => 'Aprilie',
        'May' => 'Mai',
        'June' => 'Iunie',
        'July' => 'Iulie',
        'August' => 'August',
        'September' => 'Septembrie',
        'October' => 'Octombrie',
        'November' => 'Noiembrie',
        'December' => 'Decembrie'
    ];

    public function getFilters(): array
    {
        return [
            new TwigFilter('romanianDate', [$this, 'formatRomanianDate']),
        ];
    }

    public function formatRomanianDate(\DateTimeInterface|string $date): string
    {
        if (is_string($date)) {
            $date = new \DateTime($date);
        }

        $dayName = self::ROMANIAN_DAYS[$date->format('l')];
        $day = $date->format('d');
        $monthName = self::ROMANIAN_MONTHS[$date->format('F')];

        return sprintf('%s, %s %s', $dayName, $day, $monthName);
    }
}