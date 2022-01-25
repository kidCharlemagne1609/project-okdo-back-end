<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ApiUsersController extends AbstractController
{
    

    /**
     * Get users collection
     *
     * @Route("/api/users", name="api_users_get", methods={"GET"})
     */
    public function getUsersCollection(UserRepository $userRepository): Response
    {
        // @todo : retourner les films de la BDD
        
        // On va chercher les données
        $usersList = $userRepository->findAll();

        return $this->json(
            // les données à serializer
            $usersList,
            // status code
            200,
            // Les en-têtes de réponse à ajouter (aucune)
            [],
            // Les groupes à utiliser par le Serializer
            ['groups' => 'get_users_collection']
        );
    }

    /**
     * @Route("/api/users/{id<\d+>}", name="api_users_get_item", methods={"GET"})
     */
    public function userGet(User $user = null): Response
    {

        // 404 ?
        if ($user === null) {
            return $this->json(['error' => 'Utilisateur non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($user, Response::HTTP_OK, [], ['groups' => 'get_users_collection']);
    }

    /**
     * @Route("/api/users", name="api_users_post", methods={"POST"})
     */
    public function createItem(Request $request, SerializerInterface $serializer, ManagerRegistry $doctrine, ValidatorInterface $validator, UserPasswordHasherInterface $hasher)
    {
        // Récupérer contenu JSON
        $jsonContent = $request->getContent();

        try {
            // Désérialiser (convertir) le JSON en entité Doctrine User
            $user = $serializer->deserialize($jsonContent, User::class, 'json');
        } catch (NotEncodableValueException $e) {
            // Si le JSON fourni est "malformé" ou manquant, on prévient le client
            return $this->json(
                ['error' => 'JSON invalide'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }


        // Valider l'entité
        // @link : https://symfony.com/doc/current/validation.html#using-the-validator-service
        $errors = $validator->validate($user);

        // Y'a-t-il des erreurs ?
        if (count($errors) > 0) {
            // tableau de retour
            $errorsClean = [];
            // @Retourner des erreurs de validation propres
            /** @var ConstraintViolation $error */
            foreach ($errors as $error) {
                $errorsClean[$error->getPropertyPath()][] = $error->getMessage();
            };


            return $this->json($errorsClean, Response::HTTP_UNPROCESSABLE_ENTITY);
        }
 
            // Here, we hashed our password getting by json.
        $hashedPassword = $hasher->hashPassword($user, $user->getPassword());
            // then we send it to our bdd.
        $user->setPassword($hashedPassword); 

        
        // On sauvegarde l'entité

        $entityManager = $doctrine->getManager();
        $entityManager->persist($user);
        $entityManager->flush();

        // Get adapted responsed
        return $this->json(

            $user,

            // status code
            //en constante de classe
            Response::HTTP_CREATED,
            // REST demande un header Location + URL de la ressource 
            [
                // nom de l'en-tête + URL
                'Location' => $this->generateUrl('api_users_get_item', ['id' => $user->getId()])
            ],

            // Groupe
            ['groups' => 'create_user_item']
        );
    }
}
