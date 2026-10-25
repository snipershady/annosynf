<?php

declare(strict_types=1);

namespace App\Controller;

use App\Security\Altcha\AltchaChallengeFactory;
use App\Security\Altcha\AltchaPurpose;
use App\Security\Altcha\AltchaVerifierInterface;
use App\Security\SecurityCheck\SecurityCheckCookie;
use App\Security\SecurityCheck\SecurityCheckGate;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * "Verifica di sicurezza" prima della home per i visitatori anonimi (come la modalità Under
 * Attack di Cloudflare): il browser risolve da solo una proof of work e riceve un cookie firmato.
 * Chi è collegato non la vede mai.
 */
final class SecurityCheckController extends AbstractController
{
    #[Route('/security-check', name: 'app_security_check', methods: ['GET', 'POST'])]
    public function check(
        Request $request,
        SecurityCheckGate $gate,
        SecurityCheckCookie $cookie,
        AltchaChallengeFactory $challenges,
        AltchaVerifierInterface $verifier,
    ): Response {
        if (!$gate->isRequired($request, $this->getUser())) {
            return $this->redirectToRoute('app_dashboard');
        }

        if ($request->isMethod('POST') && $verifier->verify($request->request->getString('altcha'), AltchaPurpose::SecurityCheck)) {
            $response = $this->redirectToRoute('app_dashboard');
            $response->headers->setCookie($cookie->issue($request));

            return $response;
        }

        return $this->render('security_check/index.html.twig', [
            'altcha_challenge' => $challenges->createJson(AltchaPurpose::SecurityCheck),
            'failed' => $request->isMethod('POST'),
        ], new Response(status: $request->isMethod('POST') ? Response::HTTP_FORBIDDEN : Response::HTTP_OK));
    }
}
