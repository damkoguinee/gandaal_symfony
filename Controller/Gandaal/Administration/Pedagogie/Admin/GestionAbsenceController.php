<?php
namespace App\Controller\Gandaal\Administration\Pedagogie\Admin;

use App\Entity\Event;
use App\Entity\ControlEleve;
use App\Service\TrieService;
use App\Entity\Etablissement;
use App\Repository\UserRepository;
use App\Repository\EventRepository;
use App\Entity\HistoriqueSuppression;
use App\Repository\MatiereRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\InscriptionRepository;
use phpDocumentor\Reflection\Types\Null_;
use App\Repository\ControlEleveRepository;
use App\Repository\EtablissementRepository;
use App\Repository\HeureTravailleRepository;
use App\Repository\PersonnelActifRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\ClasseRepartitionRepository;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Repository\PaiementSalairePersonnelRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/gandaal/administration/pedagogie/admin/gestion/absence')]
class GestionAbsenceController extends AbstractController
{
    #[Route('/{etablissement}', name: 'app_gandaal_administration_pedagogie_admin_gestion_absence')]
    public function index(Etablissement $etablissement,  ClasseRepartitionRepository $classeRep, ControlEleveRepository $controlRep, SessionInterface $session, Request $request): Response
    {
        $search = $request->get('search') ?:NULL;
        $type = $request->get('type') ?:NULL;
        $periode = $request->get('periode') ?:date('Y-m-d');
        $classe = $request->query->get('classe') ? $classeRep->find($request->query->get('classe')) : null;
        $controles = $controlRep->listeDesControlesParPromoParEtablissement($session->get('promo'), $etablissement, $search, $periode, $type);
        
        return $this->render('gandaal/administration/pedagogie/admin/gestion_absence/index.html.twig', [
            'etablissement' => $etablissement,
            'classes' => $classeRep->listeDesClassesParEtablissementParPromo($etablissement, $request->getSession()->get('promo')),
            'classe' => $classe,
            'controles' => $controles,
            'promo' => $session->get('promo'),
            'periode' => $periode,
            'type' => $type,
        ]);
    }

    #[Route('/show/{id}/{etablissement}', name: 'app_gandaal_administration_pedagogie_admin_gestion_absence_show', methods: ['GET'])]
    public function show(ControlEleve $controlEleve, InscriptionRepository $inscriptionRep, SessionInterface $session, Request $request, TrieService $trieService, ClasseRepartitionRepository $classeRep, Etablissement $etablissement, EntityManagerInterface $em): Response
    {
        // gestion de changement de classe de l'élève
        $classe_id = $request->get('classe_id');
         if ($classe_id) {
            // revenir sur la gestion dans le cas ou l'élève a été evalué
            $classe = $classeRep->find($classe_id);
            $inscription_eleve = $inscriptionRep->findOneBy(['id' => $request->get('inscription'), 'promo' => $session->get('promo')]);
            $inscription_eleve->setClasse($classe);
            $em->persist($inscription_eleve);
            $em->flush();
        }
        
        return $this->render('gandaal/administration/pedagogie/admin/gestion_absence/show.html.twig', [
            'controlEleve' => $controlEleve,
            'etablissement' => $etablissement,
            'promo' => $session->get('promo'),
        ]);
    }



    


}