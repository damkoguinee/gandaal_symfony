<?php

namespace App\Controller\Gandaal\Administration\Comptabilite\Admin;

use App\Entity\ConfigActiviteScolaire;
use App\Entity\Etablissement;
use App\Form\ConfigActiviteScolaireType;
use App\Repository\ConfigActiviteScolaireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/gandaal/administration/comptabilite/admin/config/actvite/scolaire')]
class ConfigActviteScolaireController extends AbstractController
{
    #[Route('/', name: 'app_gandaal_administration_comptabilite_admin_config_actvite_scolaire_index', methods: ['GET'])]
    public function index(ConfigActiviteScolaireRepository $configActiviteScolaireRepository): Response
    {
        return $this->render('gandaal/administration/comptabilite/admin/config_actvite_scolaire/index.html.twig', [
            'config_activite_scolaires' => $configActiviteScolaireRepository->findAll(),
        ]);
    }

    #[Route('/new/{etablissement}', name: 'app_gandaal_administration_comptabilite_admin_config_actvite_scolaire_new', methods: ['GET', 'POST'])]
    public function new(Request $request, Etablissement $etablissement, ConfigActiviteScolaireRepository $activiteRep, EntityManagerInterface $entityManager): Response
    {
        $configActiviteScolaire = new ConfigActiviteScolaire();
        $form = $this->createForm(ConfigActiviteScolaireType::class, $configActiviteScolaire);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $verif = $activiteRep->findOneBy(['nom' => $configActiviteScolaire->getNom()]);
            if ($verif) {
                $this->addFlash('warning', 'Cette activité existe déjà');
            }else{

                $configActiviteScolaire->setEtablissement($etablissement);
                $entityManager->persist($configActiviteScolaire);
                $entityManager->flush();
                $this->addFlash('success', 'Activité ajoutée avec succès :)');
                return $this->redirectToRoute('app_gandaal_administration_comptabilite_admin_activite_tarif_new', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('gandaal/administration/comptabilite/admin/config_actvite_scolaire/new.html.twig', [
            'config_activite_scolaire' => $configActiviteScolaire,
            'form' => $form,
            'etablissement' => $etablissement,
            'referer' => $request->headers->get('referer')
        ]);
    }

    #[Route('/{id}', name: 'app_gandaal_administration_comptabilite_admin_config_actvite_scolaire_show', methods: ['GET'])]
    public function show(ConfigActiviteScolaire $configActiviteScolaire): Response
    {
        return $this->render('gandaal/administration/comptabilite/admin/config_actvite_scolaire/show.html.twig', [
            'config_activite_scolaire' => $configActiviteScolaire,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_gandaal_administration_comptabilite_admin_config_actvite_scolaire_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ConfigActiviteScolaire $configActiviteScolaire, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ConfigActiviteScolaireType::class, $configActiviteScolaire);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_administration_comptabilite_admin_config_actvite_scolaire_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/administration/comptabilite/admin/config_actvite_scolaire/edit.html.twig', [
            'config_activite_scolaire' => $configActiviteScolaire,
            'form' => $form,
        ]);
    }

    #[Route('/delete/{etablissement}/{id}', name: 'app_gandaal_administration_comptabilite_admin_config_activite_scolaire_delete', methods: ['POST'])]
    public function delete(Request $request, Etablissement $etablissement, ConfigActiviteScolaire $configActiviteScolaire, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$configActiviteScolaire->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($configActiviteScolaire);
            $entityManager->flush();
        }
        $this->addFlash("success", "activité supprimée avec succès :)");
        return $this->redirectToRoute('app_gandaal_administration_comptabilite_admin_activite_tarif_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
    }
}
