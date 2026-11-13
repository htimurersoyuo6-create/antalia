<?php

namespace App\Support;

final class DogBreedGroups
{
    private const GROUPS = [
        'small' => [
            'label' => 'Küçük ırk grubu',
            'examples' => ['Chihuahua', 'Yorkshire Terrier', 'Maltese'],
            'breeds' => ['Chihuahua', 'Pomeranian', 'Yorkshire Terrier', 'Maltese', 'Shih Tzu', 'Toy Poodle'],
        ],
        'medium' => [
            'label' => 'Orta ırk grubu',
            'examples' => ['Beagle', 'Border Collie', 'Cocker Spaniel'],
            'breeds' => ['Beagle', 'Cocker Spaniel', 'Border Collie', 'French Bulldog', 'Whippet'],
        ],
        'large' => [
            'label' => 'Büyük ırk grubu',
            'examples' => ['Labrador Retriever', 'Golden Retriever', 'Kangal'],
            'breeds' => ['Labrador Retriever', 'Golden Retriever', 'German Shepherd', 'Rottweiler', 'Kangal', 'Great Dane'],
        ],
    ];

    public static function groups(): array
    {
        return self::GROUPS;
    }

    public static function breeds(): array
    {
        $breeds = [];
        foreach (self::GROUPS as $size => $group) {
            foreach ($group['breeds'] as $name) {
                $breeds[] = ['name' => $name, 'size' => $size, 'group' => $group['label']];
            }
        }

        return $breeds;
    }
}
