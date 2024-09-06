<?php

namespace App\Controller\Gandaal\AdminSite;

use App\Entity\ClasseRepartition;
use App\Entity\Etablissement;
use App\Form\ClasseRepartitionType;
use App\Repository\ClasseRepartitionRepository;
use App\Repository\CursusRepository;
use App\Repository\FormationRepository;
use App\Repository\NiveauClasseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/gandaal/admin/site/classe/repartition')]
class ClasseRepartitionController extends AbstractController
{
    #[Route('/{etablissement}', name: 'app_gandaal_admin_site_classe_repartition_index', methods: ['GET'])]
    public function index(ClasseRepartitionRepository $classeRepartitionRep, CursusRepository $cursusRep,  FormationRepository $formationRep, SessionInterface $session, Etablissement $etablissement): Response
    {
        $cursus = $cursusRep->findBy(['etablissement' => $etablissement]);
        $formations = $formationRep->findBy(['cursus' => $cursus]);
        $classes = $classeRepartitionRep->findBy(['formation' => $formations, 'promo' => $session->get('promo')]);
        return $this->render('gandaal/admin_site/classe_repartition/index.html.twig', [
            'classe_repartitions' => $classes,
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/new/{etablissement}', name: 'app_gandaal_admin_site_classe_repartition_new', methods: ['GET', 'POST'])]
    public function new(Request $request, CursusRepository $cursusRep,  FormationRepository $formationRep, EntityManagerInterface $entityManager, Etablissement $etablissement): Response
    {
        $classeRepartition = new ClasseRepartition();
        $cursus = $cursusRep->findBy(['etablissement' => $etablissement]);
        
        $formations = $formationRep->findBy(['cursus' => $cursus]);
        $form = $this->createForm(ClasseRepartitionType::class, $classeRepartition, [
            'year_choices' => $this->getYearChoices(),
            'formations' => $formations
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($classeRepartition);
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_admin_site_classe_repartition_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/admin_site/classe_repartition/new.html.twig', [
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

    #[Route('/show/{id}/{etablissement}', name: 'app_gandaal_admin_site_classe_repartition_show', methods: ['GET'])]
    public function show(ClasseRepartition $classeRepartition, Etablissement $etablissement): Response
    {
        return $this->render('gandaal/admin_site/classe_repartition/show.html.twig', [
            'classe_repartition' => $classeRepartition,
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/edit/{id}/{etablissement}', name: 'app_gandaal_admin_site_classe_repartition_edit', methods: ['GET', 'POST'])]
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

            return $this->redirectToRoute('app_gandaal_admin_site_classe_repartition_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/admin_site/classe_repartition/edit.html.twig', [
            'classe_repartition' => $classeRepartition,
            'form' => $form,
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/delete/{id}/{etablissement}', name: 'app_gandaal_admin_site_classe_repartition_delete', methods: ['POST'])]
    public function delete(Request $request, ClasseRepartition $classeRepartition, EntityManagerInterface $entityManager, Etablissement $etablissement): Response
    {
        if ($this->isCsrfTokenValid('delete'.$classeRepartition->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($classeRepartition);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gandaal_admin_site_classe_repartition_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
    }


    #[Route('/report/classe/{etablissement}', name: 'app_gandaal_admin_site_classe_repartition_report_classe', methods: ['GET'])]
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
        return $this->redirectToRoute('app_gandaal_admin_site_classe_repartition_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
    }
}
