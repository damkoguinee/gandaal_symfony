<?php

namespace App\Controller;

use App\Repository\LicenceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/home', name: 'app_home')]
    public function index(SessionInterface $session, LicenceRepository $liecenceRep): Response
    {
        $promo =$session->get('promo');

        $licence = $liecenceRep->find(1);

        if ($licence->getStatutSiteWeb() != 'actif') {
            return $this->redirectToRoute('app_gandaal_home', [], Response::HTTP_SEE_OTHER);
        }
        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
        ]);
    }
}
