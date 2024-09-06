<?php

namespace App\Controller\Gandaal\Administration\Scolarite;

use App\Entity\Eleve;
use App\Entity\Tuteur;
use App\Form\EleveType;
use App\Entity\Filiation;
use App\Form\EleveInsType;
use App\Entity\Inscription;
use App\Form\TuteurInsType;
use App\Entity\LienFamilial;
use App\Entity\DocumentEleve;
use App\Entity\Etablissement;
use App\Form\InscriptionType;
use App\Form\FiliationInsType;
use App\Form\DocumentEleveType;
use App\Form\EleveInsExterneType;
use App\Form\FiliationMereInsType;
use App\Repository\UserRepository;
use App\Entity\InscriptionActivite;
use App\Repository\EleveRepository;
use App\Repository\CursusRepository;
use App\Form\InscriptionActiviteType;
use App\Repository\FiliationRepository;
use App\Repository\PersonnelRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\InscriptionRepository;
use App\Repository\LienFamilialRepository;
use App\Repository\DocumentEleveRepository;
use App\Repository\EtablissementRepository;
use App\Repository\PaiementEleveRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\ClasseRepartitionRepository;
use App\Repository\FormationRepository;
use App\Repository\InscriptionActiviteRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\String\Slugger\AsciiSlugger;
use App\Repository\TarifActiviteScolaireRepository;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/gandaal/administration/scolarite/eleve')]
class EleveController extends AbstractController
{
    #[Route('/{etablissement}', name: 'app_gandaal_administration_scolarite_eleve_index', methods: ['GET'])]
    public function index(EleveRepository $eleveRepository, InscriptionRepository $inscriptionRep, SessionInterface $session, Request $request, Etablissement $etablissement): Response
    {
        $search = $request->get('search', null);
        $pageEnCours = $request->get('pageEncours', 1);
        $inscriptions = $inscriptionRep->listeDesElevesInscritParPromoParEtablissement($session->get('promo'), $etablissement, $search, $pageEnCours, 100);
        return $this->render('gandaal/administration/scolarite/eleve/index.html.twig', [
            'inscriptions' => $inscriptions,
            'etablissement' => $etablissement,
            'promo' => $session->get('promo')
        ]);
    }

    #[Route('/classe/{etablissement}', name: 'app_gandaal_administration_scolarite_eleve_classe', methods: ['GET'])]
    public function classe(ClasseRepartitionRepository $classeRepartitionRep, CursusRepository $cursusRep,  FormationRepository $formationRep, SessionInterface $session, Etablissement $etablissement): Response
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
        return $this->render('gandaal/administration/scolarite/eleve/classe.html.twig', [
            'classe_repartitions' => $classesParFormation,
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/index/externe/{etablissement}', name: 'app_gandaal_administration_scolarite_eleve_externe_index', methods: ['GET'])]
    public function indexExterne(EleveRepository $eleveRep, InscriptionRepository $inscriptionRep, SessionInterface $session, Request $request, Etablissement $etablissement): Response
    {
        $search = $request->get('search', null);
        $pageEnCours = $request->get('pageEncours', 1);
        $eleves = $eleveRep->rechercheEleveParEtablissementParCategorie($search, $etablissement, 'externe', $pageEnCours, 35);
        return $this->render('gandaal/administration/scolarite/eleve/externe/index.html.twig', [
            'eleves' => $eleves,
            'etablissement' => $etablissement,
            'promo' => $session->get('promo')
        ]);
    }

    #[Route('/new/{etablissement}', name: 'app_gandaal_administration_scolarite_eleve_new', methods: ['GET', 'POST'])]
    public function new(Request $request, UserRepository $userRep, ClasseRepartitionRepository $classeRepartitionRep, EntityManagerInterface $em, EleveRepository $eleveRep, UserPasswordHasherInterface $userPasswordHasher, SessionInterface $session, FiliationRepository $filiationRep, EtablissementRepository $etablissementRep, PersonnelRepository $personnelRep, Etablissement $etablissement): Response
    {
        if ($request->get("id_user_search")){
            $search = $request->get("id_user_search");            
        }else{
            $search = "";
        }
        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('search');
            $eleves = $userRep->rechercheUserParTypeParEtablissement($search, 'eleve', $etablissement);    
            $response = [];
            foreach ($eleves as $eleve) {
                $response[] = [
                    'nom' => ucwords($eleve->getPrenom())." ".strtoupper($eleve->getNom()),
                    'id' => $eleve->getId()
                ]; 
            }
            return new JsonResponse($response);
        }

       // Initialiser les objets nécessaires

        if ($request->get("id_user_search")) {
            $search = $request->get("id_user_search");
            $frere = $eleveRep->find($search);   
            if ($frere) {
                $session->set('lien', $frere);        
                // Vérifiez si les filiations sont chargées
                $filiations = $frere->getFiliations();
                if ($filiations === null) {
                    throw new \Exception('Les filiations de l\'élève ne sont pas chargées correctement.');
                }
        
                foreach ($filiations as $filiation) {
                    if ($filiation->getLienFamilial() === 'père') {
                        $session->set('filiation', $filiation);
                    } else {
                        $session->set('filiation_mere', $filiation);
                    }
                }
        
                // Vérifiez si les tuteurs sont chargés
                $tuteurs = $frere->getTuteurs();
                if ($tuteurs === null) {
                    throw new \Exception('Les tuteurs de l\'élève ne sont pas chargés correctement.');
                }
        
                foreach ($tuteurs as $tuteur) {
                    $session->set('tuteur', $tuteur);
                }
        
                return $this->redirectToRoute('app_gandaal_administration_scolarite_eleve_new', [
                    'etablissement' => $etablissement->getId(),
                    'step' => 'step_two',
                ]);
            }
        }

        // Utilitaire pour récupérer l'objet depuis la session ou créer un nouveau si non présent
        $getSessionOrNew = function ($session, $key, $class) {
            return $session->has($key) ? $session->get($key) : new $class();
        };

        // Récupérer les objets depuis la session ou créer de nouveaux objets
        $eleve_ins = $getSessionOrNew($session, 'eleve_ins', Eleve::class);
        $filiation = $getSessionOrNew($session, 'filiation', Filiation::class);
        $filiation_mere = $getSessionOrNew($session, 'filiation_mere', Filiation::class);
        $tuteur = $getSessionOrNew($session, 'tuteur', Tuteur::class);
        $inscription = $getSessionOrNew($session, 'inscription', Inscription::class);

        $document = $getSessionOrNew($session, 'document', DocumentEleve::class);

        // Créer les formulaires
        $form_eleve = $this->createForm(EleveInsType::class, $eleve_ins);
        $form_filiation = $this->createForm(FiliationInsType::class, $filiation);
        $form_filiation_mere = $this->createForm(FiliationMereInsType::class, $filiation_mere);
        $form_tuteur = $this->createForm(TuteurInsType::class, $tuteur);
        $form_inscription = $this->createForm(InscriptionType::class, $inscription, ['classes' => $classeRepartitionRep->findAll()]);
        $form_document = $this->createForm(DocumentEleveType::class, $document);
      
        $matricule = $eleve_ins->getMatricule() ? $eleve_ins->getMatricule() : 'suivant';

        // Déterminer l'étape actuelle
        $step = $request->query->get('step', 'step_one');
        if ($request->isMethod('POST')) {
            if ($request->request->has('step_one')) {
                $form_eleve->handleRequest($request);
                $form_document->handleRequest($request);
                if ($form_eleve->isSubmitted() && $form_eleve->isValid()) {
                    // Stocker les données de la première étape dans la session                    
                    $eleve_ins->setTypeUser('eleve')
                            ->setStatut('actif')
                            ->setEtablissement($etablissement)
                            ->setMatricule($matricule)
                            ->setUsername($matricule)
                            ->setRoles(['ROLE_ELEVE'])
                            ->setPassword(
                                $userPasswordHasher->hashPassword(
                                    $eleve_ins,
                                    $matricule
                                )
                            );
                    $fichier = $form_eleve->get("photo")->getData();
                    if ($fichier) {
                        $nomFichier= pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME);
                        $slugger = new AsciiSlugger();
                        $nouveauNomFichier = $slugger->slug($nomFichier);
                        $nouveauNomFichier .="_".uniqid();
                        $nouveauNomFichier .= "." .$fichier->guessExtension();
                        $fichier->move($this->getParameter("dossier_eleves"),$nouveauNomFichier);
                        $eleve_ins->setPhoto($nouveauNomFichier);
                    }
                    $session->set('eleve_ins', $eleve_ins);

                    $fichier_document = $form_document->get("nom")->getData();
                    if ($fichier_document) {
                        $nomFichier= pathinfo($fichier_document->getClientOriginalName(), PATHINFO_FILENAME);
                        $slugger = new AsciiSlugger();
                        $nouveauNomFichier = $slugger->slug($nomFichier);
                        $nouveauNomFichier .="_".uniqid();
                        $nouveauNomFichier .= "." .$fichier_document->guessExtension();
                        $fichier_document->move($this->getParameter("dossier_eleves"),$nouveauNomFichier);
                        $document->setNom($nouveauNomFichier);
                        $eleve_ins->addDocumentEleve($document);
                        $em->persist($document);

                    }
                    $document->setEleve($eleve_ins);
                    
                    return $this->redirectToRoute('app_gandaal_administration_scolarite_eleve_new', [
                        'etablissement' => $etablissement->getId(),
                        'step' => 'step_two',
                    ]);
                }
            } elseif ($request->request->has('step_two')) {
                $form_filiation->handleRequest($request);
                $form_filiation_mere->handleRequest($request);
                $form_tuteur->handleRequest($request);
                if ($form_filiation_mere->isSubmitted()) {
                    // Stocker les données de la deuxième étape dans la session
                    $filiation1 = new Filiation();
                    $filiation1->setNom($filiation->getNom())
                            ->setPrenom($filiation->getPrenom())
                            ->setTelephone($filiation->getTelephone())
                            ->setEmail($filiation->getEmail())
                            ->setProfession($filiation->getProfession())
                            ->setLieuTravail($filiation->getLieuTravail())
                            ->setSexe('m')
                            ->setTypeUser('parent')
                            ->setLienFamilial('père')
                            ->setStatut('actif')
                            ->setEtablissement($etablissement);
                    $filiation1->getTelephone() ? $filiation1->getTelephone() : $filiation1->setTelephone($eleve_ins->getTelephone());
                    $filiation1->getAdresse() ? $filiation1->getAdresse() : $filiation1->setAdresse($eleve_ins->getAdresse());
                    $filiation1->getVille() ? $filiation1->getVille() : $filiation1->setVille($eleve_ins->getVille());
                    $filiation1->getPays() ? $filiation1->getPays() : $filiation1->setPays($eleve_ins->getPays());
                    $filiation1->setMatricule($matricule.'p');
                    $filiation1->setUsername($matricule.'p');
                    $filiation1->setRoles(['ROLE_PARENT']);
                    $filiation1->setPassword(
                        $userPasswordHasher->hashPassword(
                            $filiation1,
                            $matricule
                        )
                    );
                    
                    $eleve_ins->addFiliation($filiation1);
                    $session->set('filiation', $filiation1);
                    
                    $filiation_mere1 = new Filiation();
                    $filiation_mere1->setNom($filiation_mere->getNom())
                            ->setPrenom($filiation_mere->getPrenom())
                            ->setTelephone($filiation_mere->getTelephone())
                            ->setEmail($filiation_mere->getEmail())
                            ->setProfession($filiation_mere->getProfession())
                            ->setLieuTravail($filiation_mere->getLieuTravail())
                            ->setSexe('f')
                            ->setTypeUser('parent')
                            ->setLienFamilial('mère')
                            ->setStatut('actif')
                            ->setEtablissement($etablissement);
                    $filiation_mere1->getTelephone() ? $filiation_mere1->getTelephone() : $filiation_mere1->setTelephone($eleve_ins->getTelephone());
                    $filiation_mere1->getAdresse() ? $filiation_mere1->getAdresse() : $filiation_mere1->setAdresse($eleve_ins->getAdresse());
                    $filiation_mere1->getVille() ? $filiation_mere1->getVille() : $filiation_mere1->setVille($eleve_ins->getVille());
                    $filiation_mere1->getPays() ? $filiation_mere1->getPays() : $filiation_mere1->setPays($eleve_ins->getPays());
                    $filiation_mere1->setMatricule($matricule.'m');
                    $filiation_mere1->setUsername($matricule.'m');
                    $filiation_mere1->setRoles(['ROLE_PARENT']);
                    $filiation_mere1->setPassword(
                        $userPasswordHasher->hashPassword(
                            $filiation_mere1,
                            $matricule
                        )
                    );
                    $eleve_ins->addFiliation($filiation_mere1);

                    $session->set('filiation_mere', $filiation_mere1);
                    
                    $tuteur1 = new Tuteur();
                    $tuteur1->setNom($tuteur1->getNom() ? $tuteur1->getNom() : $filiation->getNom() )
                            ->setPrenom($tuteur1->getPrenom() ? $tuteur1->getPrenom() : $filiation->getPrenom())
                            ->setTelephone($tuteur1->getTelephone() ? $tuteur1->getTelephone() : $filiation->getTelephone())
                            ->setEmail($tuteur1->getEmail() ? $tuteur1->getEmail() : $filiation->getEmail())
                            ->setProfession($tuteur1->getProfession() ? $tuteur1->getProfession() : $filiation->getProfession())
                            ->setLieuTravail($tuteur1->getLieuTravail() ? $tuteur1->getLieuTravail() : $filiation->getLieuTravail())
                            ->setSexe($tuteur1->getNom() ? 'm' : 'm')
                            ->setTypeUser('tuteur')
                            ->setLienFamilial('tuteur')
                            ->setStatut('actif');
                    
                            $tuteur1->getAdresse() ? $tuteur1->getAdresse() : $tuteur1->setAdresse($eleve_ins->getAdresse());
                            $tuteur1->getVille() ? $tuteur1->getVille() : $tuteur1->setVille($eleve_ins->getVille());
                            $tuteur1->getPays() ? $tuteur1->getPays() : $tuteur1->setPays($eleve_ins->getPays());
                            $tuteur1->setMatricule($matricule.'t');
                            $tuteur1->setUsername($matricule.'t');
                            $tuteur1->setRoles(['ROLE_PARENT']);
                            $tuteur1->setPassword(
                                $userPasswordHasher->hashPassword(
                                    $tuteur1,
                                    $matricule
                                )
                            );
                    $eleve_ins->addTuteur($tuteur1);                    
                    $session->set('tuteur', $tuteur1);

                    $inscription->setPromo($session->get('promo'))
                                ->setType('inscription')
                                ->setDateInscription(new \DateTime("now"))
                                ->setStatut('actif')
                                ->setEtatScol('admis')
                                ->setSaisiePar($personnelRep->find($this->getUser()));
                    $eleve_ins->addInscription($inscription);
                    $session->set('inscription', $inscription);
                    
                    // Redirection vers l'étape suivante
                    // return $this->redirectToRoute('app_gandaal_administration_scolarite_eleve_new', [
                    //     'etablissement' => $etablissement->getId(),
                    //     'step' => 'step_three',
                    // ]);

                    return $this->redirectToRoute('app_gandaal_administration_scolarite_eleve_new_final', [
                        'etablissement' => $etablissement->getId(),
                    ]);
                }
            } elseif ($request->request->has('step_three')) {
                $form_inscription->handleRequest($request);
                if ($form_inscription->isSubmitted() && $form_inscription->isValid()) {
                    // Stocker les données de la troisième étape dans la session
                    $inscription->setPromo($session->get('promo'))
                                ->setType('inscription')
                                ->setDateInscription(new \DateTime("now"))
                                ->setStatut('actif')
                                ->setEtatScol('admis')
                                ->setSaisiePar($personnelRep->find($this->getUser()));
                    $eleve_ins->addInscription($inscription);
                    $session->set('inscription', $inscription);
                    // Redirection vers l'étape suivante

                    return $this->redirectToRoute('app_gandaal_administration_scolarite_eleve_new_final', [
                        'etablissement' => $etablissement->getId(),
                    ]);
                    
                    
                }
            } elseif ($request->request->has('previous_step')) {
                if ($step == 'step_two') {
                    $step = 'step_one';
                } elseif ($step == 'step_three') {
                    $step = 'step_two';
                } elseif ($step == 'step_four') {
                    $step = 'step_three';
                }
                // Redirection vers l'étape précédente
                return $this->redirectToRoute('app_gandaal_administration_scolarite_eleve_new', [
                    'etablissement' => $etablissement->getId(),
                    'step' => $step,
                ]);
            }
        }


        return $this->render('gandaal/administration/scolarite/eleve/new.html.twig', [
            'step' => $step,
            'eleve_ins' => $eleve_ins,
            'form_eleve' => $form_eleve->createView(),
            'filiation' => $filiation,
            'form_filiation' => $form_filiation->createView(),
            'filiation_mere' => $filiation_mere,
            'form_filiation_mere' => $form_filiation_mere->createView(),
            'tuteur' => $tuteur,
            'form_tuteur' => $form_tuteur->createView(),
            'inscription' => $inscription,
            'form_inscription' => $form_inscription->createView(),
            'document' => $document,
            'form_document' => $form_document,
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/new/final/{etablissement}', name: 'app_gandaal_administration_scolarite_eleve_new_final', methods: ['GET', 'POST'])]
    public function newInscription(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $userPasswordHasher, SessionInterface $session, InscriptionRepository $inscriptionRep, ClasseRepartitionRepository $classeRepartitionRep, EleveRepository $eleveRep,  Etablissement $etablissement): Response
    {
        $eleve_ins = $session->get('eleve_ins');
        $form_eleve = $this->createForm(EleveInsType::class, $eleve_ins);
        $form_eleve->handleRequest($request);

        $document = $session->get('document');
        $form_document = $this->createForm(DocumentEleveType::class, $document);
        $form_document->handleRequest($request);

        $filiation = $session->get('filiation');
        $form_filiation = $this->createForm(FiliationInsType::class, $filiation);
        $form_filiation->handleRequest($request);

        $filiation_mere = $session->get('filiation_mere');
        $form_filiation_mere = $this->createForm(FiliationMereInsType::class, $filiation_mere);
        $form_filiation_mere->handleRequest($request);

        $tuteur = $session->get('tuteur');
        $form_tuteur = $this->createForm(TuteurInsType::class, $tuteur);
        $form_tuteur->handleRequest($request);

        $inscription = $session->get('inscription');

        $classes = $classeRepartitionRep->listeDesClassesParEtablissementParPromo($etablissement, $session->get('promo'));
        $form_inscription = $this->createForm(InscriptionType::class, $inscription, ['classes' => $classes]);
        $form_inscription->handleRequest($request);

        if ($form_eleve->isSubmitted()) {
            // Générer le matricule
            if (is_string($session->get('promo')) && !empty($session->get('promo'))) {
                // Utiliser substr pour obtenir les deux derniers caractères
                $currentYear = substr($session->get('promo'), -2);
            } else {
                $currentYear = (new \DateTime())->format('y');
            }
            $maxInscription = $inscriptionRep->findCountInscriptionId('inscription', $etablissement, $session->get('promo'));
            $formattedMaxInscription = sprintf('%04d', $maxInscription + 1); 
            $generatedMatricule = $etablissement->getInitial() . $currentYear . $formattedMaxInscription;
            $matricule = $eleve_ins->getMatricule();
            if ($matricule == 'suivant') {
                $matricule = $generatedMatricule;
            }
            $eleve_ins->setEtablissement($etablissement)
                        ->setMatricule($matricule)
                        ->setUsername($matricule);            

            $filiation->setEtablissement($etablissement)
                    ->setNom($filiation->getNom())
                    ->setPrenom($filiation->getPrenom())          
                    ->setMatricule($matricule.'p')           
                    ->setUsername($matricule.'p');             
                

            $filiation_mere->setEtablissement($etablissement)
                    ->setNom($filiation_mere->getNom())
                    ->setPrenom($filiation_mere->getPrenom())
                    ->setMatricule($matricule.'m')
                    ->setUsername($matricule.'m');

                    

            if (!$tuteur->getNom() or !$tuteur->getPrenom()) {
                $tuteur->setPrenom($filiation->getPrenom())
                    ->setNom($filiation->getNom())
                    ->setMatricule($matricule.'t')
                    ->setUsername($matricule.'t');            
                    
            }
            $tuteur->setEtablissement($etablissement)
                ->setUsername($matricule.'t'); 

            $inscription->setSaisiePar($this->getUser())
                    ->setEtablissement($etablissement);
                    
            $entityManager->persist($eleve_ins);

            // Associer les filiations et les tuteurs à l'élève
           
            $eleve_ins->addFiliation($filiation);
            $eleve_ins->addFiliation($filiation_mere);
            $eleve_ins->addTuteur($tuteur);

            if ($session->get('lien')) {
                $lien_familial = new LienFamilial();

                $frere = $eleveRep->find($session->get('lien'));

                $lien_familial->addEleve($frere);
                $lien_familial->addEleve($eleve_ins);                
                $entityManager->persist($lien_familial);
            }            
            $entityManager->flush();
            $id = $inscriptionRep->findMaxId($etablissement);

            // Nettoyage de la session
            $session->remove('eleve_ins');
            $session->remove('filiation');
            $session->remove('filiation_mere');
            $session->remove('tuteur');
            $session->remove('inscription');
            $session->remove('document');
            $session->remove('lien');

            $this->addFlash("success", "Elève ajouté avec succès :)");
            return $this->redirectToRoute('app_gandaal_administration_scolarite_eleve_show', ['etablissement' => $etablissement->getId(), 'id' => $id], Response::HTTP_SEE_OTHER);
        }
        return $this->render('gandaal/administration/scolarite/eleve/new.html.twig', [
            'eleve' => $eleve_ins,
            'form' => $form_eleve,
            'eleve_ins' => $eleve_ins,
            'form_eleve' => $form_eleve,
            'filiation' => $filiation,
            'form_filiation' => $form_filiation,
            'filiation_mere' => $filiation_mere,
            'form_filiation_mere' => $form_filiation_mere,
            'tuteur' => $tuteur,
            'form_tuteur' => $form_tuteur,
            'inscription' => $inscription,
            'form_inscription' => $form_inscription,
            'etablissement' => $etablissement,
            'step' => 'end'
        ]);
    }


    #[Route('/show/{etablissement}/{id}', name: 'app_gandaal_administration_scolarite_eleve_show', methods: ['GET'])]
    public function show(Inscription $inscription, PaiementEleveRepository $paiementRep, LienFamilialRepository $lienFamilialRep, SessionInterface $session, InscriptionActiviteRepository $inscriptionActiviteRep, DocumentEleveRepository $documentRep, Etablissement $etablissement): Response
    {
        // foreach ($inscription->getEleve()->getLienFamilials() as  $liensFamilials) {
        //     foreach ($liensFamilials->getEleve() as $eleve) {
        //         if ($eleve != $inscription->getEleve()) {
        //             dd($eleve->getLienFamilials());
        //             $liens = $lienFamilialRep->findBy(['eleve' => $eleve]);
        //         }
        //         # code...
        //         dd($liens);
        //     }
        // }
        // dd($liens);
        $documents = $documentRep->findBy(['eleve' => $inscription->getEleve()->getId()]);
        $cumulPaiements = $paiementRep->cumulPaiementEleveGroupeParType($inscription, $session->get('promo'));
        $activites = $inscriptionActiviteRep->findBy(['eleve' => $inscription->getEleve(), 'promo' => $session->get('promo')]);
        return $this->render('gandaal/administration/scolarite/eleve/show.html.twig', [
            'inscription' => $inscription,
            'etablissement' => $etablissement,
            'documents' => $documents,
            'promo' => $session->get('promo'),
            'cumulPaiements' => $cumulPaiements,
            'activites' => $activites
        ]);
    }
    #[Route('/edit/{etablissement}/{id}', name: 'app_gandaal_administration_scolarite_eleve_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Eleve $eleve, SessionInterface $session, ClasseRepartitionRepository $classeRepartitionRep, EntityManagerInterface $entityManager, Etablissement $etablissement): Response
    {
        $filiation_pere = new Filiation();
        $filiation_mere = new Filiation();
        foreach ($eleve->getFiliations() as  $filiation) {
            if ($filiation->getLienFamilial() == 'père') {
                $filiation_pere = $filiation;
            }else{
                $filiation_mere = $filiation;
            }
        }

        $tuteur = new Tuteur();
        foreach ($eleve->getTuteurs() as  $value) {
           
            $tuteur = $value;
            
        }
        $form = $this->createForm(EleveType::class, $eleve);
        $form->handleRequest($request);

        
        $form_eleve = $this->createForm(EleveInsType::class, $eleve);
        $form_eleve->handleRequest($request);

        $document = new DocumentEleve();
        $form_document = $this->createForm(DocumentEleveType::class, $document);
        $form_document->handleRequest($request);

        $form_filiation = $this->createForm(FiliationInsType::class, $filiation_pere);
        $form_filiation->handleRequest($request);

        $form_filiation_mere = $this->createForm(FiliationMereInsType::class, $filiation_mere);
        $form_filiation_mere->handleRequest($request);

        $form_tuteur = $this->createForm(TuteurInsType::class, $tuteur);
        $form_tuteur->handleRequest($request);
        $inscription = new Inscription();
        foreach ($eleve->getInscriptions() as  $value) {
            // recupération de l'inscription de cette année
            if ($value->getPromo() == $session->get('promo')) {
                $inscription = $value;
                break;
            }
        }

        $inscription = $inscription;
        $classes = $classeRepartitionRep->listeDesClassesParEtablissementParPromo($etablissement, $session->get('promo'));
        $form_inscription = $this->createForm(InscriptionType::class, $inscription, ['classes' => $classes]);
        $form_inscription->handleRequest($request);

        if ($form_eleve->isSubmitted()) {
            $fichier = $form_eleve->get("photo")->getData();
            if ($fichier) {
                $nomFichier= pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME);
                $slugger = new AsciiSlugger();
                $nouveauNomFichier = $slugger->slug($nomFichier);
                $nouveauNomFichier .="_".uniqid();
                $nouveauNomFichier .= "." .$fichier->guessExtension();
                $fichier->move($this->getParameter("dossier_eleves"),$nouveauNomFichier);
                $eleve->setPhoto($nouveauNomFichier);
            }
            
            $entityManager->flush();

            $this->addFlash("success", "Elève modifié avec succès :)");
            return $this->redirectToRoute('app_gandaal_administration_scolarite_eleve_show', ['etablissement' => $etablissement->getId(), 'id' => $inscription->getId()], Response::HTTP_SEE_OTHER);

        }

        return $this->render('gandaal/administration/scolarite/eleve/edit.html.twig', [
            'eleve' => $eleve,
            'form' => $form_eleve,
            'form_eleve' => $form_eleve,
            'filiation' => $filiation,
            'form_filiation' => $form_filiation,
            'filiation_mere' => $filiation_mere,
            'form_filiation_mere' => $form_filiation_mere,
            'tuteur' => $tuteur,
            'form_tuteur' => $form_tuteur,
            'inscription' => $inscription,
            'form_inscription' => $form_inscription,
            'etablissement' => $etablissement,
        ]);
    }


    #[Route('/new/externe/{etablissement}', name: 'app_gandaal_administration_scolarite_eleve_externe_new', methods: ['GET', 'POST'])]
    public function newExterne(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $userPasswordHasher, SessionInterface $session, InscriptionActiviteRepository $inscriptionActiviteRep, TarifActiviteScolaireRepository $tarifScolariteRep, ClasseRepartitionRepository $classeRepartitionRep, UserRepository $userRep, EleveRepository $eleveRep,  Etablissement $etablissement): Response
    {

        if ($request->get("id_user_search")){
            $search = $request->get("id_user_search");            
        }else{
            $search = "";
        }
        if ($request->isXmlHttpRequest()) {
            $search = $request->query->get('search');
            $eleves = $userRep->rechercheUserParTypeParEtablissement($search, 'eleve', $etablissement);    
            $response = [];
            foreach ($eleves as $eleve) {
                $response[] = [
                    'nom' => ucwords($eleve->getPrenom())." ".strtoupper($eleve->getNom()),
                    'id' => $eleve->getId()
                ]; 
            }
            return new JsonResponse($response);
        }

        if ($request->get("id_user_search")) {
            $search = $request->get("id_user_search");
            $frere = $eleveRep->find($search);   
            if ($frere) {
                $session->set('lien', $frere);        
                // Vérifiez si les filiations sont chargées
                $filiations = $frere->getFiliations();
                if ($filiations === null) {
                    throw new \Exception('Les filiations de l\'élève ne sont pas chargées correctement.');
                }
        
                foreach ($filiations as $filiation) {
                    if ($filiation->getLienFamilial() === 'père') {
                        $session->set('filiation', $filiation);
                    } else {
                        $session->set('filiation_mere', $filiation);
                    }
                }
        
                // Vérifiez si les tuteurs sont chargés
                $tuteurs = $frere->getTuteurs();
                if ($tuteurs === null) {
                    throw new \Exception('Les tuteurs de l\'élève ne sont pas chargés correctement.');
                }
        
                foreach ($tuteurs as $tuteur) {
                    $session->set('tuteur', $tuteur);
                }
        
                return $this->redirectToRoute('app_gandaal_administration_scolarite_eleve_new', [
                    'etablissement' => $etablissement->getId(),
                    'step' => 'step_two',
                ]);
            }
        }

        // Utilitaire pour récupérer l'objet depuis la session ou créer un nouveau si non présent
        $getSessionOrNew = function ($session, $key, $class) {
            return $session->has($key) ? $session->get($key) : new $class();
        };

        $filiation = $getSessionOrNew($session, 'filiation', Filiation::class);
        $filiation_mere = $getSessionOrNew($session, 'filiation_mere', Filiation::class);

        $eleve = new Eleve() ;
        $form_eleve = $this->createForm(EleveInsExterneType::class, $eleve);
        $form_eleve->handleRequest($request);

        $document = $session->get('document');
        $form_document = $this->createForm(DocumentEleveType::class, $document);
        $form_document->handleRequest($request);

        // $filiation = new Filiation();
        $form_filiation = $this->createForm(FiliationInsType::class, $filiation);
        $form_filiation->handleRequest($request);

        // $filiation_mere = new Filiation();
        $form_filiation_mere = $this->createForm(FiliationMereInsType::class, $filiation_mere);
        $form_filiation_mere->handleRequest($request);

        $inscription_activite = new InscriptionActivite();
        $form_activite = $this->createForm(InscriptionActiviteType::class, $inscription_activite);
        $form_activite->handleRequest($request); 

        if ($form_eleve->isSubmitted()) {

            // Générer le matricule
            if (is_string($session->get('promo')) && !empty($session->get('promo'))) {
                // Utiliser substr pour obtenir les deux derniers caractères
                $currentYear = substr($session->get('promo'), -2);
            } else {
                $currentYear = (new \DateTime())->format('y');
            }
            $maxInscription = $eleveRep->findCountIdExterne($etablissement, $session->get('promo'));
            $formattedMaxInscription = sprintf('%04d', $maxInscription + 1); 
            $generatedMatricule = 'ext' . $currentYear . $formattedMaxInscription;
            $matricule = $eleve->getMatricule();
            
            $matricule = $generatedMatricule;

            $eleve->setTypeUser('eleve')
                    ->setStatut('actif')
                    ->setEtablissement($etablissement)
                    ->setMatricule($matricule)
                    ->setUsername($matricule)
                    ->setRoles(['ROLE_ELEVE'])
                    ->setPassword(
                        $userPasswordHasher->hashPassword(
                            $eleve,
                            $matricule
                        )
                    );
            $fichier = $form_eleve->get("photo")->getData();
            if ($fichier) {
                $nomFichier= pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME);
                $slugger = new AsciiSlugger();
                $nouveauNomFichier = $slugger->slug($nomFichier);
                $nouveauNomFichier .="_".uniqid();
                $nouveauNomFichier .= "." .$fichier->guessExtension();
                $fichier->move($this->getParameter("dossier_eleves"),$nouveauNomFichier);
                $eleve->setPhoto($nouveauNomFichier);
            }
            

            $entityManager->persist($eleve);

            $fichier_document = $form_document->get("nom")->getData();
            if ($fichier_document) {
                $nomFichier= pathinfo($fichier_document->getClientOriginalName(), PATHINFO_FILENAME);
                $slugger = new AsciiSlugger();
                $nouveauNomFichier = $slugger->slug($nomFichier);
                $nouveauNomFichier .="_".uniqid();
                $nouveauNomFichier .= "." .$fichier_document->guessExtension();
                $fichier_document->move($this->getParameter("dossier_eleves"),$nouveauNomFichier);
                $document->setNom($nouveauNomFichier);
                $eleve->addDocumentEleve($document);
                $entityManager->persist($document);

            }

            $filiation->setNom($filiation->getNom())
                    ->setPrenom($filiation->getPrenom())
                    ->setTelephone($filiation->getTelephone())
                    ->setEmail($filiation->getEmail())
                    ->setProfession($filiation->getProfession())
                    ->setLieuTravail($filiation->getLieuTravail())
                    ->setSexe('m')
                    ->setTypeUser('parent')
                    ->setLienFamilial('père')
                    ->setStatut('actif')
                    ->setEtablissement($etablissement);
            $filiation->getTelephone() ? $filiation->getTelephone() : $filiation->setTelephone($eleve->getTelephone());
            $filiation->getAdresse() ? $filiation->getAdresse() : $filiation->setAdresse($eleve->getAdresse());
            $filiation->getVille() ? $filiation->getVille() : $filiation->setVille($eleve->getVille());
            $filiation->getPays() ? $filiation->getPays() : $filiation->setPays($eleve->getPays());
            $filiation->setMatricule($matricule.'p');
            $filiation->setUsername($matricule.'p');
            $filiation->setRoles(['ROLE_PARENT']);
            $filiation->setPassword(
                $userPasswordHasher->hashPassword(
                    $filiation,
                    $matricule
                )
            );
                    
            $eleve->addFiliation($filiation);
           
            $filiation_mere->setNom($filiation_mere->getNom())
                    ->setPrenom($filiation_mere->getPrenom())
                    ->setTelephone($filiation_mere->getTelephone())
                    ->setEmail($filiation_mere->getEmail())
                    ->setProfession($filiation_mere->getProfession())
                    ->setLieuTravail($filiation_mere->getLieuTravail())
                    ->setSexe('f')
                    ->setTypeUser('parent')
                    ->setLienFamilial('mère')
                    ->setStatut('actif')
                    ->setEtablissement($etablissement);
            $filiation_mere->getTelephone() ? $filiation_mere->getTelephone() : $filiation_mere->setTelephone($eleve->getTelephone());
            $filiation_mere->getAdresse() ? $filiation_mere->getAdresse() : $filiation_mere->setAdresse($eleve->getAdresse());
            $filiation_mere->getVille() ? $filiation_mere->getVille() : $filiation_mere->setVille($eleve->getVille());
            $filiation_mere->getPays() ? $filiation_mere->getPays() : $filiation_mere->setPays($eleve->getPays());
            $filiation_mere->setMatricule($matricule.'m');
            $filiation_mere->setUsername($matricule.'m');
            $filiation_mere->setRoles(['ROLE_PARENT']);
            $filiation_mere->setPassword(
                $userPasswordHasher->hashPassword(
                    $filiation_mere,
                    $matricule
                )
            );
            $eleve->addFiliation($filiation_mere);


            $entityManager->persist($filiation);
            $entityManager->persist($filiation_mere);           
                    

            if ($session->get('lien')) {
                $lien_familial = new LienFamilial();

                $frere = $eleveRep->find($session->get('lien'));

                $lien_familial->addEleve($frere);
                $lien_familial->addEleve($eleve);                
                $entityManager->persist($lien_familial);
            }
            
            $tarifs = $request->get('tarifs');
            foreach ($tarifs as $value) {
                $tarif = $tarifScolariteRep->find($value);
                $inscription_activite_final = new InscriptionActivite();

                $inscription_activite_final->setTarifActivite($tarif)
                    ->setPromo($session->get('promo'))
                    ->setEtablissement($etablissement)
                    ->setDateSaisie(new \DateTime("now"))
                    ->setSaisiePar($this->getUser())
                    ->setDateInscription($inscription_activite->getDateInscription())
                    ->setRemise($inscription_activite->getRemise())
                    ->setTypeEleve("externe")
                    ->setStatut('actif');
                $eleve->addInscriptionActivite($inscription_activite_final);
                $entityManager->persist($inscription_activite_final);
           }
            $entityManager->flush();
            $id = $eleveRep->findMaxId($etablissement);
            

            // Nettoyage de la session
            $session->remove('eleve_ins');
            $session->remove('filiation');
            $session->remove('filiation_mere');
            $session->remove('document');
            $session->remove('lien');


            $this->addFlash("success", "Elève ajouté avec succès :)");
            return $this->redirectToRoute('app_gandaal_administration_scolarite_eleve_externe_show', ['etablissement' => $etablissement->getId(), 'id' => $id], Response::HTTP_SEE_OTHER);
        }
        $inscription_activite = new InscriptionActivite();
        $tarifs = $tarifScolariteRep->tarifActiviteParEtablissementParPromo($etablissement, $session->get('promo'));
        $activites = $inscriptionActiviteRep->findBy(['eleve' => $search, 'promo' => $session->get('promo')]);
        return $this->render('gandaal/administration/scolarite/eleve/externe/new.html.twig', [
            'eleve' => $eleve,
            'form' => $form_eleve,
            'form_eleve' => $form_eleve,
            'filiation' => $filiation,
            'form_filiation' => $form_filiation,
            'filiation_mere' => $filiation_mere,
            'form_filiation_mere' => $form_filiation_mere,
            'etablissement' => $etablissement,
            'step' => 'end',
            'activites' => $activites,
            'tarifs' => $tarifs,
            'inscription_activite' => $inscription_activite,
            'form_activite' => $form_activite
        ]);
    }

    #[Route('/show/externe/{etablissement}/{id}', name: 'app_gandaal_administration_scolarite_eleve_externe_show', methods: ['GET'])]
    public function showExterne(Eleve $eleve, PaiementEleveRepository $paiementRep, LienFamilialRepository $lienFamilialRep, SessionInterface $session, InscriptionActiviteRepository $inscriptionActiviteRep, DocumentEleveRepository $documentRep, Etablissement $etablissement): Response
    {
        $documents = $documentRep->findBy(['eleve' => $eleve->getId()]);
        // $cumulPaiements = $paiementRep->cumulPaiementEleveGroupeParType($inscription, $session->get('promo'));
        $activites = $inscriptionActiviteRep->findBy(['eleve' => $eleve, 'promo' => $session->get('promo')]);
        return $this->render('gandaal/administration/scolarite/eleve/externe/show.html.twig', [
            'eleve' => $eleve,
            'etablissement' => $etablissement,
            'documents' => $documents,
            'promo' => $session->get('promo'),
            // 'cumulPaiements' => $cumulPaiements,
            'activites' => $activites
        ]);
    }

    

    #[Route('/edit/externe/{etablissement}/{id}', name: 'app_gandaal_administration_scolarite_eleve_externe_edit', methods: ['GET', 'POST'])]
    public function editExterne(Request $request, Eleve $eleve, SessionInterface $session, TarifActiviteScolaireRepository $tarifScolariteRep, InscriptionActiviteRepository $inscriptionActiviteRep, EntityManagerInterface $entityManager, Etablissement $etablissement): Response
    {
        $filiation_pere = new Filiation();
        $filiation_mere = new Filiation();
        foreach ($eleve->getFiliations() as  $filiation) {
            if ($filiation->getLienFamilial() == 'père') {
                $filiation_pere = $filiation;
            }else{
                $filiation_mere = $filiation;
            }
        }

        
        $form_eleve = $this->createForm(EleveInsExterneType::class, $eleve);
        $form_eleve->handleRequest($request);

        $document = new DocumentEleve();
        $form_document = $this->createForm(DocumentEleveType::class, $document);
        $form_document->handleRequest($request);

        $form_filiation = $this->createForm(FiliationInsType::class, $filiation_pere);
        $form_filiation->handleRequest($request);

        $form_filiation_mere = $this->createForm(FiliationMereInsType::class, $filiation_mere);
        $form_filiation_mere->handleRequest($request);

        // $inscription_activite = new InscriptionActivite();
        // $form_activite = $this->createForm(InscriptionActiviteType::class, $inscription_activite);
        // $form_activite->handleRequest($request); 

        if ($form_eleve->isSubmitted()) {
            $fichier = $form_eleve->get("photo")->getData();
            if ($fichier) {
                $nomFichier= pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME);
                $slugger = new AsciiSlugger();
                $nouveauNomFichier = $slugger->slug($nomFichier);
                $nouveauNomFichier .="_".uniqid();
                $nouveauNomFichier .= "." .$fichier->guessExtension();
                $fichier->move($this->getParameter("dossier_eleves"),$nouveauNomFichier);
                $eleve->setPhoto($nouveauNomFichier);
            }
            
            $entityManager->flush();

            $this->addFlash("success", "Elève modifié avec succès :)");
            return $this->redirectToRoute('app_gandaal_administration_scolarite_eleve_externe_show', ['etablissement' => $etablissement->getId(), 'id' => $eleve->getId()], Response::HTTP_SEE_OTHER);

        }

        // $inscription_activite = new InscriptionActivite();
        // $tarifs = $tarifScolariteRep->tarifActiviteParEtablissementParPromo($etablissement, $session->get('promo'));
        // $activites = $inscriptionActiviteRep->findBy(['eleve' => $eleve, 'promo' => $session->get('promo')]);

        return $this->render('gandaal/administration/scolarite/eleve/externe/edit.html.twig', [
            'eleve' => $eleve,
            'form' => $form_eleve,
            'form_eleve' => $form_eleve,
            'filiation' => $filiation,
            'form_filiation' => $form_filiation,
            'filiation_mere' => $filiation_mere,
            'form_filiation_mere' => $form_filiation_mere,
            'etablissement' => $etablissement,
            // 'activites' => $activites,
            // 'tarifs' => $tarifs,
            // 'inscription_activite' => $inscription_activite,
            // 'form_activite' => $form_activite
        ]);
    }

    #[Route('/delete/{etablissement}/{id}', name: 'app_gandaal_administration_scolarite_eleve_delete', methods: ['POST'])]
    public function delete(Request $request, Eleve $eleve, EntityManagerInterface $entityManager, Etablissement $etablissement): Response
    {
        if ($this->isCsrfTokenValid('delete'.$eleve->getId(), $request->getPayload()->getString('_token'))) {

            if ($eleve->getPhoto()) {
                $ancienFichier=$this->getParameter("dossier_eleves")."/".$eleve->getPhoto();
                if (file_exists($ancienFichier)) {
                    unlink($ancienFichier);
                }
            }
            $entityManager->remove($eleve);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gandaal_administration_scolarite_eleve_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/delete/externe/{etablissement}/{id}', name: 'app_gandaal_administration_scolarite_eleve_externe_delete', methods: ['POST'])]
    public function deleteExterne(Request $request, Eleve $eleve, EntityManagerInterface $entityManager, Etablissement $etablissement): Response
    {
        if ($this->isCsrfTokenValid('delete'.$eleve->getId(), $request->getPayload()->getString('_token'))) {

            if ($eleve->getPhoto()) {
                $ancienFichier=$this->getParameter("dossier_eleves")."/".$eleve->getPhoto();
                if (file_exists($ancienFichier)) {
                    unlink($ancienFichier);
                }
            }
            $entityManager->remove($eleve);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_gandaal_administration_scolarite_inscription_activite_index', ['etablissement' => $etablissement->getId()], Response::HTTP_SEE_OTHER);
    }
}
