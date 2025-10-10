<?php

namespace App\Controller;
use App\Entity\Order;
use App\Form\OrderType;
use App\Repository\CategoryRepository;
use App\Repository\CityRepository;
use App\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use symfony\Component\HttpFoundation\JsonResponse;

final class OrderController extends AbstractController
{
    #[Route('/order', name: 'app_order')]
    public function index(Request $request, CategoryRepository $categoryRepository,
    ProductRepository $productRepository, SessionInterface $session,
    EntityManagerInterface $entityManager): Response
    {
        $cart = $session->get('cart', []);
        $cartWithData = [];

        foreach ($cart as $id => $quantity) {
            $product = $productRepository->find($id);

            if($product) {
                $cartWithData[] = [
                    'product' => $product,
                    'quantity' => $quantity
                ];
            }
        }

        // calculer le prix total du panier
        $total = array_sum(array_map(function ($item) {
            return $item['product']->getPrice() * $item['quantity'];
        }, $cartWithData));

        $order = new Order();
        $form = $this->createForm(OrderType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
            $order->setCreatedAt(new \DateTimeImmutable());
            $order->setTotalPrice($total + $order->getShippingPrice());

            $entityManager->persist($order);
            $entityManager->flush();

            return $this->redirectToRoute('app_home', [], Response::HTTP_SEE_OTHER);

        }



        return $this->render('order/index.html.twig', [
            'controller_name' => 'OrderController',
            'form' => $form,
            'total' =>$total,
            'categories'=> $categoryRepository->findAll(),
        ]);

    }

    #[Route('/get_shipping_cost', name: 'get_shipping_cost', methods: ['POST'])]
    public function getShippingCost(Request $request, CityRepository $cityRepository): JsonResponse
    {
        $id = $request->request->get('city');
        $city = $cityRepository->find($id);
        $cost = $city->getShippingCost() ?? 10.00; //10€ par défaut

        return new JsonResponse(['shippingCost' => $cost]);
    }

}
