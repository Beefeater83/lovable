<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Services\OtpService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class OtpController extends AbstractController
{
    public function __construct(private OtpService $otpService)
    {
    }

    #[Route('/iam/otp', name: 'request_otp', methods: ['POST'])]
    public function requestOtp(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $email = $data['email'] ?? false;

        if (!$email) {
            return $this->json(null, Response::HTTP_BAD_REQUEST);
        }

        $success = $this->otpService->sendOtp($email);

        return $this->json(null, $success ? Response::HTTP_OK : Response::HTTP_NOT_FOUND);
    }

    #[Route('/iam/otp-verification', name: 'verification_otp', methods: ['POST'])]
    public function verifyOtp(Request $request): void
    {
    }

}
