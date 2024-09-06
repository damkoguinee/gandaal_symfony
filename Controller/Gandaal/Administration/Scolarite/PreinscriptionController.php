<?php

namespace App\Controller\Gandaal\Administration\Scolarite;

use App\Entity\DocumentEleve;
use App\Entity\Eleve;
use App\Entity\Tuteur;
use App\Form\EleveType;
use App\Form\TuteurType;
use App\Entity\Filiation;
use App\Form\EleveInsType;
use App\Entity\Inscription;
use App\Form\FiliationType;
use App\Form\TuteurInsType;
use App\Entity\Etablissement;
use App\Entity\LienFamilial;
use App\Entity\Preinscription;
use App\Form\DocumentEleveType;
use App\Form\InscriptionType;
use App\Form\FiliationInsType;
use App\Form\FiliationMereInsType;
use App\Repository\UserRepository;
use App\Repository\EleveRepository;
use App\Form\FiliationTuteurInsType;
use App\Form\PreinscriptionType;
use App\Repository\PersonnelRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\InscriptionRepository;
use App\Repository\DocumentEleveRepository;
use App\Repository\EtablissementRepository;
use Doctrine\DBAL\Driver\Mysqli\Initializer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\ClasseRepartitionRepository;
use App\Repository\FiliationRepository;
use App\Repository\LienFamilialRepository;
use App\Repository\PreinscriptionRepository;
use App\Repository\TuteurRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/gandaal/administration/scolarite/preinscription')]
class PreinscriptionController extends AbstractController
{
    #[Route('/{etablissement}', name: 'app_gandaal_administration_scolarite_preinscription_index', methods: ['GET'])]
    public function index(EleveRepository $eleveRepository, PreinscriptionRepository $preinscriptionRep, SessionInterface $session, Request $request, Etablissement $etablissement): Response
    {
        $search = $request->get('search', null);
        $pageEnCours = $request->get('pageEncours', 1);
        $inscriptions = $preinscriptionRep->listeDesElevesPreinscritParPromoParEtablissement($session->get('promo'), $etablissement, $search, $pageEnCours, 25);
        return $this->render('gandaal/administration/scolarite/preinscription/index.html.twig', [
            'inscriptions' => $inscriptions,
            'etablissement' => $etablissement
        ]);
    }

    #[Route('/new/{etablissement}', name: 'app_gandaal_administration_scolarite_preinscription_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SessionInterface $session, Etablissement $etablissement): Response
    {
        $preinscription = new Preinscription();
        $form = $this->createForm(PreinscriptionType::class, $preinscription);
        $form->handleRequest($request);

        if ($form->isSubmitted() and $form->isValid()) {
            $preinscription->setEtablissement($etablissement)
                ->setPromo($session->get('promo'));
            
            $fichier = $form->get("photo")->getData();
            if ($fichier) {
                $nomFichier= pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME);
                $slugger = new AsciiSlugger();
                $nouveauNomFichier = $slugger->slug($nomFichier);
                $nouveauNomFichier .="_".uniqid();
                $nouveauNomFichier .= "." .$fichier->guessExtension();
                $fichier->move($this->getParameter("dossier_eleves"),$nouveauNomFichier);
                $preinscription->setPhoto($nouveauNomFichier);
            }
            $entityManager->persist($preinscription);
            $entityManager->flush();           

            $this->addFlash("success", "Elève pré-inscrit avec succès :)");
            return $this->redirectToRoute('app_gandaal_administration_scolarite_preinscription_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/administration/scolarite/preinscription/new.html.twig', [
            'preinscription' => $preinscription,
            'form' => $form,
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/show/{etablissement}/{id}', name: 'app_gandaal_administration_scolarite_preinscription_show', methods: ['GET'])]
    public function show(Preinscription $preinscription, SessionInterface $session, Etablissement $etablissement): Response
    {
        return $this->render('gandaal/administration/scolarite/preinscription/show.html.twig', [
            'inscription' => $preinscription,
            'etablissement' => $etablissement,
            'promo' => $session->get('promo'),
        ]);
    }

    #[Route('/edit/{etablissement}/{id}', name: 'app_gandaal_administration_scolarite_preinscription_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Preinscription $preinscription, SessionInterface $session, ClasseRepartitionRepository $classeRepartitionRep, EntityManagerInterface $entityManager, Etablissement $etablissement): Response
    {
       
        $form = $this->createForm(PreinscriptionType::class, $preinscription);
        $form->handleRequest($request);

        if ($form->isSubmitted() and $form->isValid()) {
            $fichier = $form->get("photo")->getData();
            if ($fichier) {
                $nomFichier= pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME);
                $slugger = new AsciiSlugger();
                $nouveauNomFichier = $slugger->slug($nomFichier);
                $nouveauNomFichier .="_".uniqid();
                $nouveauNomFichier .= "." .$fichier->guessExtension();
                $fichier->move($this->getParameter("dossier_eleves"),$nouveauNomFichier);
                $preinscription->setPhoto($nouveauNomFichier);
            }
            
            $entityManager->flush();

            $this->addFlash("success", "Elève modifié avec succès :)");
            return $this->redirectToRoute('app_gandaal_administration_scolarite_preinscription_show', ['etablissement' => $etablissement->getId(), 'id' => $preinscription->getId()], Response::HTTP_SEE_OTHER);

        }

        return $this->render('gandaal/administration/scolarite/preinscription/edit.html.twig', [
            'preinscription' => $preinscription,
            'form' => $form,
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/delete/{etablissement}/{id}', name: 'app_gandaal_administration_scolarite_preinscription_delete', methods: ['POST'])]
    public function delete(Request $request, Preinscription $preinscription, EntityManagerInterface $entityManager, Etablissement $etablissement): Response
    {
        if ($this->isCsrfTokenValid('delete'.$preinscription->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($preinscription);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gandaal_administration_scolarite_preinscription_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
    }
}
