<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\GoalType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    #[Route('/register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        $required = ['username', 'email', 'password', 'firstName', 'lastName', 'goalType'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->json(['error' => sprintf('Field "%s" is required', $field)], Response::HTTP_BAD_REQUEST);
            }
        }

        $goalType = GoalType::tryFrom($data['goalType']);
        if (!$goalType) {
            return $this->json([
                'error' => 'Invalid goalType. Must be one of: weight_loss, muscle_gain, maintenance, endurance',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($em->getRepository(User::class)->findOneBy(['email' => $data['email']])) {
            return $this->json(['error' => 'Email already in use'], Response::HTTP_CONFLICT);
        }

        if ($em->getRepository(User::class)->findOneBy(['username' => $data['username']])) {
            return $this->json(['error' => 'Username already taken'], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setUsername($data['username']);
        $user->setEmail($data['email']);
        $user->setFirstName($data['firstName']);
        $user->setLastName($data['lastName']);
        $user->setGoalType($goalType);
        $user->setPassword($passwordHasher->hashPassword($user, $data['password']));

        if (isset($data['birthdate'])) {
            $user->setBirthdate(new \DateTime($data['birthdate']));
        }
        if (isset($data['heightCm'])) {
            $user->setHeightCm($data['heightCm']);
        }
        if (isset($data['currentWeightKg'])) {
            $user->setCurrentWeightKg($data['currentWeightKg']);
        }

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
            }
            return $this->json(['errors' => $messages], Response::HTTP_BAD_REQUEST);
        }

        $em->persist($user);
        $em->flush();

        return $this->json([
            'message' => 'User registered successfully',
            'userId' => $user->getId(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        // Handled by lexik_jwt_authentication via the security firewall.
        // This method is never reached — it exists only so the route is registered.
        return $this->json(['error' => 'Missing credentials'], Response::HTTP_UNAUTHORIZED);
    }

    #[Route('/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        return $this->json(['message' => 'Logged out successfully']);
    }
}
