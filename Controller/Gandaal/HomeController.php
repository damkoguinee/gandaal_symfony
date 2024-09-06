<?php

namespace App\Controller\Gandaal;

use App\Entity\Etablissement;
use App\Repository\CursusRepository;
use App\Repository\EntrepriseRepository;
use App\Repository\EtablissementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


#[Route('/gandaal/home')]
class HomeController extends AbstractController
{
    #[Route('/', name: 'app_gandaal_home')]
    public function index(EntrepriseRepository $entrepriseRep, EtablissementRepository $etablissementRep): Response
    {
        $entreprise = $entrepriseRep->findOneBy([]);
        $etablissements = $etablissementRep->findAll();
        return $this->render('gandaal/home/index.html.twig', [
            'entreprise' => $entreprise,
            'etablissements' => $etablissements,
        ]);
    }

    #[Route('/etablissement/home/{etablissement}', name: 'app_gandaal_etablissement_home')]
    public function etablissement(Etablissement $etablissement, CursusRepository $cursusRep): Response
    {
        $cursus = $cursusRep->findBy(['etablissement' => $etablissement]);
        return $this->render('gandaal/home/accueil_etablissement.html.twig', [
            'etablissement' => $etablissement,
            'cursus' => $cursus,
        ]);
    }

    #[Route('/administration/home/{etablissement}', name: 'app_gandaal_administration_home')]
    public function homeDirection(Etablissement $etablissement, CursusRepository $cursusRep): Response
    {
        return $this->render('gandaal/home/accueil_administration.html.twig', [
            'etablissement' => $etablissement,
        ]);
    }
}
