<?php

namespace App\Controller\Gandaal\Administration\Pedagogie;

use App\Service\TrieService;
use App\Entity\Etablissement;
use App\Entity\ClasseRepartition;
use App\Form\ClasseRepartitionType;
use App\Repository\CursusRepository;
use App\Repository\FormationRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\InscriptionRepository;
use App\Repository\NiveauClasseRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\ClasseRepartitionRepository;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


#[Route('/gandaal/administration/pedagogie/admin/classe/repartition')]
class ClasseRepartitionController extends AbstractController
{
    #[Route('/{etablissement}', name: 'app_gandaal_administration_pedagogie_admin_classe_repartition_index', methods: ['GET'])]
    public function index(ClasseRepartitionRepository $classeRepartitionRep, CursusRepository $cursusRep,  FormationRepository $formationRep, SessionInterface $session, Etablissement $etablissement): Response
    {
        $cursus = $cursusRep->findBy(['etablissement' => $etablissement]);
        $formations = $formationRep->findBy(['cursus' => $cursus]);
        $classes = $classeRepartitionRep->findBy(['formation' => $formations, 'promo' => $session->get('promo')]);

        // Grouper les paiements par mode de paiement
        $classesParFormation = [];
        foreach ($classes as $classe) {
            $formation = $classe->getFormation()->getNom();
            if (!isset($classesParFormation[$formation])) {
                $classesParFormation[$formation] = [];
            }
            $classesParFormation[$formation][] = $classe;
        }
        return $this->render('gandaal/administration/pedagogie/admin/classe_repartition/index.html.twig', [
            'classe_repartitions' => $classesParFormation,
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/new/{etablissement}', name: 'app_gandaal_administration_pedagogie_admin_classe_repartition_new', methods: ['GET', 'POST'])]
    public function new(Request $request, CursusRepository $cursusRep,  FormationRepository $formationRep, EntityManagerInterface $entityManager, Etablissement $etablissement): Response
    {
        $classeRepartition = new ClasseRepartition();
        $cursus = $cursusRep->findBy(['etablissement' => $etablissement]);
        // dd($this->getYearChoices());
        
        $formations = $formationRep->findBy(['cursus' => $cursus]);
        $form = $this->createForm(ClasseRepartitionType::class, $classeRepartition, [
            'year_choices' => $this->getYearChoices(),
            'formations' => $formations
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($classeRepartition);
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_administration_pedagogie_admin_classe_repartition_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/administration/pedagogie/admin/classe_repartition/new.html.twig', [
            'classe_repartition' => $classeRepartition,
            'form' => $form,
            'etablissement' => $etablissement,
        ]);
    }

    private function getYearChoices(): array
    {
        $currentYear = (int) date('Y');
        $startYear = 2023;
        $years = [];

        for ($year = $startYear; $year <= $currentYear + 1; $year++) {
            $years["$year - " . ($year + 1)] = $year+1;
        }

        return $years;
    }

    #[Route('/show/{id}/{etablissement}', name: 'app_gandaal_administration_pedagogie_admin_classe_repartition_show', methods: ['GET'])]
    public function show(ClasseRepartition $classeRepartition, InscriptionRepository $inscriptionRep, SessionInterface $session, Request $request, TrieService $trieService, ClasseRepartitionRepository $classeRep, Etablissement $etablissement, EntityManagerInterface $em): Response
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
        $search = $request->get('search');
        // $inscriptions = $inscriptionRep->listeDesElevesInscritParPromoParEtablissementParClassePaginated($session->get('promo'), $etablissement, $classeRepartition, $search, 1, 10000);
        // dd($inscriptions, $session->get('promo'), $etablissement, $classeRepartition);

        $inscriptions = $classeRepartition->getInscriptions()->toArray(); // Convertir la collection en tableau
        $sortedInscriptions = $trieService->trieInscriptions($inscriptions);

        // // Trier par prénom, puis nom, puis matricule
        // usort($inscriptions, function ($a, $b) {
        //     $prenomCompare = strcmp($a->getEleve()->getPrenom(), $b->getEleve()->getPrenom());
        //     if ($prenomCompare !== 0) {
        //         return $prenomCompare;
        //     }

        //     $nomCompare = strcmp($a->getEleve()->getNom(), $b->getEleve()->getNom());
        //     if ($nomCompare !== 0) {
        //         return $nomCompare;
        //     }

        //     return strcmp($a->getEleve()->getMatricule(), $b->getEleve()->getMatricule());
        // });

        // $inscriptions = $classeRepartition->getInscriptions();
        $liste_classes = $classeRep->findBy(['formation' => $classeRepartition->getFormation(), 'promo' => $session->get('promo')], ['nom' => 'ASC']);
        
        return $this->render('gandaal/administration/pedagogie/admin/classe_repartition/show.html.twig', [
            'classe_repartition' => $classeRepartition,
            'etablissement' => $etablissement,
            'inscriptions' => $sortedInscriptions,
            'promo' => $session->get('promo'),
            'liste_classes' => $liste_classes,
            'search' => $search,
        ]);
    }

    #[Route('/edit/{id}/{etablissement}', name: 'app_gandaal_administration_pedagogie_admin_classe_repartition_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, CursusRepository $cursusRep,  FormationRepository $formationRep, ClasseRepartition $classeRepartition, EntityManagerInterface $entityManager, Etablissement $etablissement): Response
    {
        $cursus = $cursusRep->findBy(['etablissement' => $etablissement]);
        
        $formations = $formationRep->findBy(['cursus' => $cursus]);
        $form = $this->createForm(ClasseRepartitionType::class, $classeRepartition, [
            'year_choices' => $this->getYearChoices(),
            'formations' => $formations
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_administration_pedagogie_admin_classe_repartition_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/administration/pedagogie/admin/classe_repartition/edit.html.twig', [
            'classe_repartition' => $classeRepartition,
            'form' => $form,
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/delete/{id}/{etablissement}', name: 'app_gandaal_administration_pedagogie_admin_classe_repartition_delete', methods: ['POST'])]
    public function delete(Request $request, ClasseRepartition $classeRepartition, EntityManagerInterface $entityManager, Etablissement $etablissement): Response
    {
        if ($this->isCsrfTokenValid('delete'.$classeRepartition->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($classeRepartition);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gandaal_administration_pedagogie_admin_classe_repartition_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
    }


    #[Route('/report/classe/{etablissement}', name: 'app_gandaal_administration_pedagogie_admin_classe_repartition_report_classe', methods: ['GET'])]
    public function reportClasse(Request $request, ClasseRepartitionRepository $classeRepartitionRep, CursusRepository $cursusRep,  FormationRepository $formationRep, SessionInterface $session, EntityManagerInterface $em, Etablissement $etablissement): Response
    {
        $cursus = $cursusRep->findBy(['etablissement' => $etablissement]);
        
        $formations = $formationRep->findBy(['cursus' => $cursus]);
        $classes = $classeRepartitionRep->findBy(['formation' => $formations, 'promo' => ($session->get('promo') -1)]);

        foreach ($classes as $value) {
            $verif = $classeRepartitionRep->findOneBy(['nom' => $value->getNom(), 'promo' => $session->get('promo')]);

            if (!$verif) {
                $classe = new ClasseRepartition();
                $classe->setFormation($value->getFormation())
                    ->setNom($value->getNom())
                    ->setPromo($session->get('promo'))
                    ->setResponsable($value->getResponsable());
                $em->persist($classe);
            }
        }

        $em->flush();
        return $this->redirectToRoute('app_gandaal_administration_pedagogie_admin_classe_repartition_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
    }
}
