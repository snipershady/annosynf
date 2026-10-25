<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Security\SecurityCheck\SecurityCheckGate;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Home dell'applicazione (§B.11.1). Gli anonimi passano prima dalla verifica di sicurezza e poi
 * vedono la presentazione del progetto; gli account non ancora abilitati solo l'avviso di attesa.
 */
final class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function index(Request $request, SecurityCheckGate $securityCheck): Response
    {
        $current = $this->getUser();

        if ($securityCheck->isRequired($request, $current)) {
            return $this->redirectToRoute('app_security_check');
        }

        if ($current instanceof User && !$current->isActive()) {
            return $this->render('dashboard/pending.html.twig', ['user' => $current]);
        }

        if (!$this->isGranted('ROLE_VIEWER')) {
            return $this->render('dashboard/welcome.html.twig');
        }

        return $this->render('dashboard/index.html.twig');
    }
}
