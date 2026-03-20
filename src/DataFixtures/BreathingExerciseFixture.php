<?php

namespace App\DataFixtures;

use App\Entity\BreathingExercise;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class BreathingExerciseFixture extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $exercises = [
            [
                'name'    => '7-4-8 (Relaxation profonde)',
                'inhale'  => 7,
                'hold'    => 4,
                'exhale'  => 8,
            ],
            [
                'name'    => '5-5 (Coherence cardiaque)',
                'inhale'  => 5,
                'hold'    => 0,
                'exhale'  => 5,
            ],
            [
                'name'    => '4-6 (Apaisement)',
                'inhale'  => 4,
                'hold'    => 0,
                'exhale'  => 6,
            ],
        ];

        foreach ($exercises as $data) {
            // Skip if an exercise with the same name already exists
            $existing = $manager->getRepository(BreathingExercise::class)->findOneBy(['name' => $data['name']]);
            if ($existing !== null) {
                continue;
            }

            $exercise = new BreathingExercise();
            $exercise->setName($data['name']);
            $exercise->setInhaleDuration($data['inhale']);
            $exercise->setHoldDuration($data['hold']);
            $exercise->setExhaleDuration($data['exhale']);
            $exercise->setActive(true);

            $manager->persist($exercise);
        }

        $manager->flush();
    }
}
