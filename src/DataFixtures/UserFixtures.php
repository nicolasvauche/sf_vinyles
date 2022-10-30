<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    private UserPasswordHasherInterface $hasher;

    public function __construct(UserPasswordHasherInterface $hasher)
    {
        $this->hasher = $hasher;
    }

    public function load(ObjectManager $manager): void
    {
        $admin = new User();
        $admin->setPseudo('admin')
            ->setEmail('admin@admin.com')
            ->setPassword($this->hasher->hashPassword($admin, 'admin'))
            ->setRoles(['ROLE_ADMIN'])
            ->setIsActive(true);
        $manager->persist($admin);

        $user = new User();
        $user->setPseudo('nicolas')
            ->setEmail('nicolas@user.com')
            ->setPassword($this->hasher->hashPassword($user, 'nicolas'))
            ->setIsActive(true);
        $manager->persist($user);

        $user = new User();
        $user->setPseudo('sophie')
            ->setEmail('sophie@user.com')
            ->setPassword($this->hasher->hashPassword($user, 'sophie'))
            ->setIsActive(true);
        $manager->persist($user);

        $manager->flush();
    }
}
