<?php

namespace App\Controller;
use App\Entity\Order;
use App\Entity\OrderProducts;
use App\Form\OrderType;
use App\Repository\CategoryRepository;
use App\Repository\CityRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class OrderController extends AbstractController
{
    public function __construct(private MailerInterface $mailer){}



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
            if(!empty($cart))
            {
            $order->setCreatedAt(new \DateTimeImmutable());
            $order->setTotalPrice($total + $order->getShippingPrice());

            $entityManager->persist($order);
            $entityManager->flush();

            foreach ($cart as $id => $quantity) {
                $product = $productRepository->find($id);

                if ($product) {
                    $orderProduct = new OrderProducts();
                    $orderProduct->setOrder($order);
                    $orderProduct->setProduct($product);
                    $orderProduct->setQte($quantity);
                    $orderProduct->setPrice($product->getPrice());

                    $order->addOrderProduct($orderProduct);

                    $entityManager->persist($orderProduct);
                    $entityManager->flush();
                }
            }
            $session->remove('cart');

            $html = $this->renderView('mail/orderConfirm.html.twig', [
                'order'=>$order
            ]);

            $email = (new Email())
            ->from('sio-shoes@edouardgand.fr')
            ->to('jdubromelle@edouardgand.fr')
            ->subject('Confirmation de commande Sio-shoes')
            ->html($html);

            $this->mailer->send($email);

            return $this->redirectToRoute('order_message', [], Response::HTTP_SEE_OTHER);
            }
            else
            {
                $this->addflash('danger','Impossible de commander, le panier est vide !');
                return $this->redirectToRoute('app_cart', [], Response::HTTP_SEE_OTHER);
            }
        }




        return $this->render('order/index.html.twig', [
            'controller_name' => 'OrderController',
            'form' => $form,
            'total' =>$total,
            'categories'=> $categoryRepository->findAll(),
        ]);

    }

    #[Route('/get_shipping_cost', name: 'get_shipping_cost', methods: ['POST'])]
    public function getShippingCost(Request $request, CityRepository $cityRepository):JsonResponse
    {
        $id = $request->request->get('city');
        $city = $cityRepository->find($id);
        $cost = $city->getShippingCost() ?? 10.00; //10€ par défaut

        return new JsonResponse(['shippingCost' => $cost]);
    }

    #[Route('/order-message', name: 'order_message')]
    public function orderMessage(CategoryRepository $categoryRepository):Response
    {
        return $this->render('order/order-message.html.twig', [
            'categories' => $categoryRepository->findAll(),
        ]);
    }



    #[Route('/editor/orders', name: 'app_orders')]
    public function getAllOrders(OrderRepository $orderRepository, CategoryRepository $categoryRepository): Response
    {
        $orders = $orderRepository -> findAll();
        return $this->render('order/orders.html.twig', [
            'categories' => $categoryRepository->findAll(),
            'orders'=>$orders
        ]);
    }

    #[Route('/editor/remove/{id}/', name: 'app_orders_remove', methods: ['POST'])]
    public function removeFromOrders(int $id,EntityManagerInterface $entityManager ,OrderRepository $orderRepository, Request $request): Response
    {
        $orders = $orderRepository -> find($id);

        $entityManager->remove($orders);
        $entityManager->flush();
        $this->addFlash('success','La catégorie a été supprimée');
        

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/editor/order/{id}/is-delivered/update', name: 'app_orders_is_delivered_update')]
    public function isDeliveredUpdate($id, OrderRepository $orderRepository,
    EntityManagerInterface $entityManager): Response
    {
        $order = $orderRepository->find($id);
        $order->setIsDelivered(true);
        $entityManager->flush();

        $this->addflash('success','La commande a été marquée comme livrée.');

        return $this->redirectToRoute('app_orders', [], Response::HTTP_SEE_OTHER);
    }


}
