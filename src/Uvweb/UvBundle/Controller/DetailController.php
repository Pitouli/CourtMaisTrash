<?php

namespace Uvweb\UvBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Uvweb\UvBundle\Entity\Comment;

class DetailController extends BaseController
{
    public function detailAction($uvname)
    {
        /** those lines allow redirection after submitting search bar form */
        if ($redirect = $this->initSearchBar()) {
            return $redirect;
        }

        $manager = $this->getDoctrine()->getManager();
        $uvRepository = $manager->getRepository("UvwebUvBundle:Uv");
        $commentRepository = $manager->getRepository('UvwebUvBundle:Comment');
        $pollRepository = $manager->getRepository('UvwebUvBundle:Poll');

        $uv = $uvRepository->findOneByName($uvname);
        if ($uv == null) throw $this->createNotFoundException("Cette UV n'existe pas ou plus");

        $comments = $commentRepository->findBy(
            array('uv' => $uv, 'moderated' => true),
            array('date' => 'desc'),
            20,
            0);

        $polls = $pollRepository->findBy(
            array('uv' => $uv),
            array('year' => 'desc'),
            4,
            0);

        $averageRate = $commentRepository->averageRate($uv);

        $comment = new Comment();
        $form = $this->createFormBuilder($comment)
            ->add('comment', 'textarea')
            ->add('interest', 'choice', array(
                'choices' => array('a' => 'Passionnant', 'b' => 'Très intéressant',
                    'c' => 'Intéressant', 'd' => 'Peu intéressant', 'e' => 'Bof', 'f' => 'Nul'),
            ))
            ->add('pedagogy', 'choice', array(
                'choices' => array('a' => 'Passionnant', 'b' => 'Très intéressant',
                    'c' => 'Intéressant', 'd' => 'Peu intéressant', 'e' => 'Bof', 'f' => 'Nul'),
            ))
            ->add('utility', 'choice', array(
                'choices' => array('a' => 'Indispensable', 'b' => 'Très importante',
                    'c' => 'Utile', 'd' => 'Pas très utile', 'e' => 'Très peu utile', 'f' => 'Inutile'),
            ))
            ->add('workamount', 'choice', array(
                'choices' => array('a' => 'Symbolique', 'b' => 'Faible',
                    'c' => 'Moyenne', 'd' => 'Importante', 'e' => 'Très importante'),
            ))
            ->add('passed', 'choice', array(
                'choices' => array('a' => 'Obtenue', 'b' => 'Ratée', 'c' => 'En cours')
            ))
            ->add('semester', 'choice', array(
                'choices' => array('a' => 'P13', 'b' => 'A12')
            ))
            ->add('globalRate', 'choice', array(
                'choices' => array('a' => '10', 'b' => '9', 'b' => '8', 'c' => '7', 'd' => '6'
                , 'e' => '5', 'f' => '4', 'g' => '3', 'h' => '2', 'i' => '1', 'j' => '0')
            ))
            ->getForm();

        $request = $this->getRequest();
        if ($request->isMethod('POST')) {
            $form->bind($request);

            if ($form->isValid()) {
                // perform some action, such as saving the task to the database
                $comment->setDate(new \DateTime());
                return $this->render('UvwebUvBundle:Uv:detail.html.twig', array(
                    'uv' => $uv,
                    'comments' => $comments,
                    'polls' => $polls,
                    'firstPoll' => $polls[0],
                    'averageRate' => $averageRate,
                    'searchbar' => $this->searchBarForm->createView(),
                    'add_comment_form' => $form->createView()
                ));
            }
        }

        return $this->render('UvwebUvBundle:Uv:detail.html.twig', array(
            'uv' => $uv,
            'comments' => $comments,
            'polls' => $polls,
            'firstPoll' => $polls[0],
            'averageRate' => $averageRate,
            'searchbar' => $this->searchBarForm->createView(),
            'add_comment_form' => $form->createView()
        ));
    }

    public function uvTitleAction()
    {
        $manager = $this->getDoctrine()->getManager();
        $uvRepository = $manager->getRepository("UvwebUvBundle:Uv");
        $uvs = $uvRepository->findBy(array('title' => ''),
            array('name' => 'desc'),
            100,
            0);
        include('uvtitlefetcher/simple_html_dom.php');
        foreach ($uvs as $uv) {

            // Include the library


            // Retrieve the DOM from a given URL
            $html = file_get_html('http://cap.utc.fr/portail_UV/detailuv.php?uv=' . $uv->getName() . '&page=uv&lang=FR');


            // Find the DIV tag with an id of "myId"
            foreach ($html->find('span#titre') as $e) {
                $arr = split(" - ", $e->innertext);
                $title = $arr[1];
                $uv->setTitle(html_entity_decode($title));

                $manager->persist($uv);
                echo $uv->getName() . " : " . $title;
                echo "<br>";
            }
        }
        $manager->flush();
        $response = new Response;
        $response->setContent("<body></body>");
        return $response;

    }

    public function uvNametoUvIdAction()
    {
        $manager = $this->getDoctrine()->getManager();

        $uvRepository = $manager->getRepository("UvwebUvBundle:Uv");
        $pollRepository = $manager->getRepository("UvwebUvBundle:Poll");

        $polls = $pollRepository->findAll();
        foreach ($polls as $poll) {

            if ($poll->getUv() != null) continue;

            $uv = $uvRepository->findOneByName($poll->getUvName());

            if ($uv != null) {
                echo "uv found : " . $uv->getName() . "<br>";
                $poll->setUv($uv);
            } else {
                echo "uv not found : " . $poll->getUvName() . "<br>";
            }

        }
        $manager->flush();

        return new Response;

    }

    public function pollSemesterToYearAction()
    {
        $manager = $this->getDoctrine()->getManager();
        $pollRepository = $manager->getRepository('UvwebUvBundle:Poll');

        $polls = $pollRepository->findAll();

        $i = 0;

        foreach ($polls as $poll) {
            if ($i >= 500) break;
            $semester = $poll->getSemester();

            if ($poll->getSeason() != "Automne" && $poll->getSeason() != "Printemps") {

                echo $poll->getSemester() . " ";
                if (strtoupper(substr($semester, 0, 1)) == 'P') {
                    echo "printemps ";
                    $poll->setSeason("Printemps");
                } else if (strtoupper(substr($semester, 0, 1)) == 'A') {
                    echo "automne ";
                    $poll->setSeason("Automne");
                } else {
                    echo "ERROR ";
                }
                echo "<br>";

                $i++;

            }

            if ($poll->getYear() == 0) {
                echo "annnee 20" . substr($semester, 1, 3) . '<br>';
                $poll->setYear('20' . substr($semester, 1, 3));
                $i++;
            }
        }

        $manager->flush();

        return new Response;
    }

    public function appDetailAction($uvname)
    {
        $manager = $this->getDoctrine()->getManager();
        $commentRepository = $manager->getRepository("UvwebUvBundle:Comment");
        $uvRepository = $manager->getRepository("UvwebUvBundle:Uv");

        $uv = $uvRepository->findOneByName($uvname);
        if ($uv == null) throw $this->createNotFoundException("Cette UV n'existe pas ou plus");

        $comments = $commentRepository->findBy(
            array('uv' => $uv, 'moderated' => true),
            array('date' => 'desc'),
            20,
            0);

        $encoders = array(new XmlEncoder(), new JsonEncoder());

        $normalizer = new GetSetMethodNormalizer();
        $normalizer->setIgnoredAttributes(array('moderator', 'date', 'last', 'utcLogin', 'password', 'email', 'id', 'moderated', 'uv', 'isadmin', 'author'));

        $normalizers = array($normalizer);

        $serializer = new Serializer($normalizers, $encoders);

        $json = $serializer->serialize($comments, 'json');

        $response = new Response;
        $response->setContent($json);

        return $response;
    }

    public function searchAction($searchtext)
    {
        if (preg_match("/^[a-zA-Z]{2}+[0-9]{2}$/", $searchtext)) {
            return $this->redirect($this->generateUrl('uvweb_uv_detail', array('uvname' => $searchtext)));
        } else {
            return $this->render('UvwebUvBundle:Uv:search.html.twig');
            echo 'not ok ' . $searchtext . '<br>';
        }
    }

    public function appListAction()
    {
        $manager = $this->getDoctrine()->getManager();
        $uvRepository = $manager->getRepository("UvwebUvBundle:Uv");

        $encoders = array(new XmlEncoder(), new JsonEncoder());
        $normalizers = array(new GetSetMethodNormalizer());

        $serializer = new Serializer($normalizers, $encoders);

        $uvs = $uvRepository->findAll();

        $response = new Response();
        $response->setContent($serializer->serialize($uvs, 'json'));
        return $response;
    }
}

?>