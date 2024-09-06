<?php

namespace App\Controller\Gandaal\Administration\Comptabilite\Admin;

use App\Entity\ConfigCaisse;
use App\Entity\Etablissement;
use App\Form\ConfigCaisseType;
use App\Repository\ConfigCaisseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/gandaal/administration/comptabilite/admin/config/caisse')]
class ConfigCaisseController extends AbstractController
{
    #[Route('/{etablissement}', name: 'app_gandaal_administration_comptabilite_admin_config_caisse_index', methods: ['GET'])]
    public function index(ConfigCaisseRepository $configCaisseRepository, Etablissement $etablissement): Response
    {
        return $this->render('gandaal/administration/comptabilite/admin/config_caisse/index.html.twig', [
            'config_caisses' => $configCaisseRepository->findBy(['etablissement' => $etablissement]),
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/new/{etablissement}', name: 'app_gandaal_administration_comptabilite_admin_config_caisse_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, Etablissement $etablissement): Response
    {
        $configCaisse = new ConfigCaisse();
        $form = $this->createForm(ConfigCaisseType::class, $configCaisse);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $configCaisse->setEtablissement($etablissement);
            $entityManager->persist($configCaisse);
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_administration_comptabilite_admin_config_caisse_index', ['etablissement' =>$etablissement->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/administration/comptabilite/admin/config_caisse/new.html.twig', [
            'config_caisse' => $configCaisse,
            'form' => $form,
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_admin_config_caisse_show', methods: ['GET'])]
    public function show(ConfigCaisse $configCaisse, Etablissement $etablissement): Response
    {
        return $this->render('gandaal/administration/comptabilite/admin/config_caisse/show.html.twig', [
            'config_caisse' => $configCaisse,
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/edit/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_admin_config_caisse_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ConfigCaisse $configCaisse, EntityManagerInterface $entityManager, Etablissement $etablissement): Response
    {
        $form = $this->createForm(ConfigCaisseType::class, $configCaisse);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $configCaisse->setEtablissement($etablissement);
            $entityManager->flush();

            return $this->redirectToRoute('app_gandaal_administration_comptabilite_admin_config_caisse_index', ['etablissement' =>$etablissement->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('gandaal/administration/comptabilite/admin/config_caisse/edit.html.twig', [
            'config_caisse' => $configCaisse,
            'form' => $form,
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/delete/{id}/{etablissement}', name: 'app_gandaal_administration_comptabilite_admin_config_caisse_delete', methods: ['POST'])]
    public function delete(Request $request, ConfigCaisse $configCaisse, EntityManagerInterface $entityManager, Etablissement $etablissement): Response
    {
        if ($this->isCsrfTokenValid('delete'.$configCaisse->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($configCaisse);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gandaal_administration_comptabilite_admin_config_caisse_index', ['etablissement' =>$etablissement->getId()], Response::HTTP_SEE_OTHER);
    }
}
