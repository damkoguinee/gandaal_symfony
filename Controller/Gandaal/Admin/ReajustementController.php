<?php

namespace App\Controller\Gandaal\Admin;

use App\Entity\CategorieMatiere;
use App\Form\CategorieMatiereType;
use App\Repository\CategorieMatiereRepository;
use App\Repository\EleveRepository;
use App\Repository\InscriptionRepository;
use App\Repository\PaiementEleveRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/gandaal/admin/reajustement')]
class ReajustementController extends AbstractController
{
    #[Route('/', name: 'app_gandaal_admin_reajustement_photo', methods: ['GET'])]
    public function index(EleveRepository $eleveRep, EntityManagerInterface $em, InscriptionRepository $inscriptionRep): Response
    {
        $eleves = $eleveRep->findAll();

        foreach ($eleves as $key => $eleve) {
            // Récupère la photo actuelle
            $photo = $eleve->getPhoto();
            
            // Utilise une expression régulière pour vérifier si la photo a déjà une extension
            if (!preg_match('/\.(jpg|jpeg|png|gif|bmp|tiff|webp)$/i', $photo)) {
                // Si aucune extension d'image n'est trouvée, ajoute '.jpg' par défaut
                $eleve->setPhoto($photo . '.jpg');
                
                // Persist la modification
                $em->persist($eleve);
            }
        }
        $em->flush();
        return $this->redirectToRoute('app_gandaal_home', [], Response::HTTP_SEE_OTHER);    
    }
}
