<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Entity\EmailConfirmationToken;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\RegistrationEmailService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegisterController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserRepository $userRepository,
        private readonly RegistrationEmailService $registrationEmailService,
    ) {
    }

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    #[OA\Post(
        path: '/api/register',
        summary: 'Register a new user',
        description: 'Creates a user account with email and password credentials.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'new.user@example.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'SecurePass123!'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: Response::HTTP_CREATED,
                description: 'User registered successfully.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'status', type: 'string', example: 'ok')]),
            ),
            new OA\Response(
                response: Response::HTTP_BAD_REQUEST,
                description: 'Invalid payload or missing required fields.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'error', type: 'string', example: 'Email and password are required.')]),
            ),
            new OA\Response(
                response: Response::HTTP_CONFLICT,
                description: 'User already exists.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'error', type: 'string', example: 'User already exists.')]),
            ),
        ],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Invalid payload.'], Response::HTTP_BAD_REQUEST);
        }

        $email = $payload['email'] ?? null;
        $password = $payload['password'] ?? null;

        if (!is_string($email) || $email === '' || !is_string($password) || $password === '') {
            return new JsonResponse(['error' => 'Email and password are required.'], Response::HTTP_BAD_REQUEST);
        }

        if ($this->userRepository->findOneBy(['email' => $email]) !== null) {
            return new JsonResponse(['error' => 'User already exists.'], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setExternalId($email);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setIsVerified(false);

        $confirmationToken = new EmailConfirmationToken($user, new DateTimeImmutable('+48 hours'));

        $this->entityManager->persist($user);
        $this->entityManager->persist($confirmationToken);
        $this->entityManager->flush();

        $this->registrationEmailService->sendConfirmationEmail($user, $confirmationToken);

        return new JsonResponse(['status' => 'ok'], Response::HTTP_CREATED);
    }
}
