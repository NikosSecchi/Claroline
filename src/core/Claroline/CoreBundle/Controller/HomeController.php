<?php

namespace Claroline\CoreBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Claroline\CoreBundle\Entity\Home\Content;
use Claroline\CoreBundle\Entity\Home\Type;
use Claroline\CoreBundle\Entity\Home\SubContent;
use Claroline\CoreBundle\Entity\Home\Content2Type;
use Claroline\CoreBundle\Entity\Home\Content2Region;
use Symfony\Component\HttpFoundation\Response;
use JMS\SecurityExtraBundle\Annotation\Secure;
use JMS\DiExtraBundle\Annotation\InjectParams;
use JMS\DiExtraBundle\Annotation\Inject;
use Claroline\CoreBundle\Manager\HomeManager;

/**
 * @TODO doc
 */
class HomeController extends Controller
{
    private $manager;

    /**
     * @InjectParams({
     *     "manager" = @Inject("claroline.manager.home_manager")
     * })
     */
    public function __construct(HomeManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Get content by id
     *
     * @Route(
     *     "/content/{content}/{type}/{subContent}",
     *     requirements={"id" = "\d+"},
     *     name="claroline_get_content_by_id_and_type",
     *     defaults={"type" = "home", "subContent" = null}
     * )
     * @ParamConverter(
     *     "content",
     *     class = "ClarolineCoreBundle:Home\Content",
     *     options = {"id" = "content"}
     * )
     * @ParamConverter(
     *     "type",
     *     class = "ClarolineCoreBundle:Home\Content2Type",
     *     options = {"content_id" = "content", "type_id" = "type"}
     * )
     * @ParamConverter(
     *     "subContent",
     *     class = "ClarolineCoreBundle:Home\SubContent",
     *     options = {"content_id" = "subContent"}
     * )
     *
     */
    public function contentAction(Content $content, Content2Type $type = null, SubContent $subContent = null)
    {
        return $this->manager->render(
            'ClarolineCoreBundle:Home/types:'.(is_object($type) ? $type->getType()->getName() : 'default').'.html.twig',
            $this->manager->getContent($content, $type, $subContent),
            true
        );
    }

    /**
     * @route("/type/{type}", name="claro_get_content_by_type")
     * @route("/", name="claro_index", defaults={"type" = "home"})
     *
     * @ParamConverter(
     *     "type",
     *     class = "ClarolineCoreBundle:Home\Content2Type",
     *     options = {"mapping": {null: "back", "type_id": "name"}}
     * )
     * @ParamConverter(
     *     "regions",
     *     class = "ClarolineCoreBundle:Home\Content2Region",
     *     options = {"mapping": {null: "back"}}
     * )
     *
     * @Template("ClarolineCoreBundle:Home:home.html.twig")
     */
    public function homeAction(Content2Type $type, $regions)
    {
        return array(
            "region" => $this->manager->getRegions($regions),
            "content" => "hola" //$this->manager->contentLayout($type)->getContent()
        );
    }

    /**
     *
     * @route("/types", name="claroline_types_manager")
     * @Secure(roles="ROLE_ADMIN")
     *
     * @Template("ClarolineCoreBundle:Home:home.html.twig")
     */
    public function typesAction()
    {
        $manager = $this->getDoctrine()->getManager();

        $types = $manager->getRepository("ClarolineCoreBundle:Home\Type")->findAll();

        $variables = array(
            "region" => $this->getRegions(),
            "content" => $this->render(
                "ClarolineCoreBundle:Home:types.html.twig",
                array('types' => $types)
            )->getContent()
        );

        return $variables;
    }

    /**
     * Create new content by POST method. This is used by ajax.
     * The response is the id of the new content in success, otherwise the response is the false word in a string.
     *
     * @route("/content/create", name="claroline_content_create")
     * @Secure(roles="ROLE_ADMIN")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function createAction()
    {
        $request = $this->get('request');
        $response = "false";

        if ($request->get('title') or $request->get('text')) {

            $manager = $this->getDoctrine()->getManager();

            $content = new Content();

            $content->setTitle($request->get('title'));
            $content->setContent($request->get('text'));
            $content->setGeneratedContent($request->get('generated'));

            $manager->persist($content);

            if ($request->get('father')) {
                $father = $manager->getRepository("ClarolineCoreBundle:Home\Content")->find($request->get('father'));

                $first = $manager->getRepository("ClarolineCoreBundle:Home\SubContent")->findOneBy(
                    array('back' => null, 'father' => $father)
                );

                $subContent = new SubContent($first);
                $subContent->setFather($father);
                $subContent->SetChild($content);

                $manager->persist($subContent);

            } else {

                $type = $manager->getRepository("ClarolineCoreBundle:Home\Type")->findOneBy(
                    array('name' => $request->get('type'))
                );

                $first = $manager->getRepository("ClarolineCoreBundle:Home\Content2Type")->findOneBy(
                    array('back' => null, 'type' => $type)
                );

                $contentType = new Content2Type($first);

                $contentType->setContent($content);
                $contentType->setType($type);

                $manager->persist($contentType);
            }

            $manager->flush();

            $response = $content->getId();
        }

        return new Response($response);
    }

    /**
     * Update a content by POST method. This is used by ajax.
     * The response is the word true in a string in success, otherwise false.
     *
     * @route("/content/update/{id}", name="claroline_content_update")
     * @Secure(roles="ROLE_ADMIN")
     *
     * @param \String $id The id of the content.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function updateAction($id)
    {
        $manager = $this->getDoctrine()->getManager();

        $content = $manager->getRepository("ClarolineCoreBundle:Home\Content")->findOneBy(array('id' => $id));

        $request = $this->get('request');

        $content->setTitle($request->get('title'));
        $content->setContent($request->get('text'));
        $content->setGeneratedContent($request->get('generated_content'));

        if ($request->get('size') and $request->get('type')) {
            $type = $manager->getRepository("ClarolineCoreBundle:Home\Type")->findOneBy(
                array('name' => $request->get('type'))
            );

            $contentType = $manager->getRepository("ClarolineCoreBundle:Home\Content2Type")->findOneBy(
                array('content' => $content, 'type' => $type)
            );

            $contentType->setSize($request->get('size'));
            $manager->persist($contentType);
        }

        $content->setModified();

        $manager->persist($content);
        $manager->flush();

        return new Response("true");
    }

    /**
     * Reorder contents in types. This method is used by ajax.
     * The response is the word true in a string in success, otherwise false.
     *
     * @param \String $type The type of the content.
     * @param \String $a The id of the content 1.
     * @param \String $b The id of the content 2.
     *
     * @route(
     *     "/content/reorder/{type}/{a}/{b}",
     *     requirements={"a" = "\d+"},
     *     name="claroline_content_reorder")
     *
     * @Secure(roles="ROLE_ADMIN")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function reorderAction($type, $a, $b)
    {
        $manager = $this->getDoctrine()->getManager();

        $a = $manager->getRepository("ClarolineCoreBundle:Home\Content")->find($a);
        $b = $manager->getRepository("ClarolineCoreBundle:Home\Content")->find($b);

        $type = $manager->getRepository("ClarolineCoreBundle:Home\Type")->findOneBy(array('name' => $type));

        $a = $manager->getRepository("ClarolineCoreBundle:Home\Content2Type")->findOneBy(
            array(
                'type' => $type,
                'content' => $a
            )
        );

        $a->detach();

        if ($b) {
            $b = $manager->getRepository("ClarolineCoreBundle:Home\Content2Type")->findOneBy(
                array(
                    'type' => $type,
                    'content' => $b
                )
            );

            $a->setBack($b->getBack());
            $a->setNext($b);

            if ($b->getBack()) {
                $b->getBack()->setNext($a);
            }

            $b->setBack($a);

        } else {
            $b = $manager->getRepository("ClarolineCoreBundle:Home\Content2Type")->findOneBy(
                array(
                    'type' => $type,
                    'next' => null
                )
            );

            $a->setNext($b->getNext());
            $a->setBack($b);

            $b->setNext($a);
        }

        $manager->persist($a);
        $manager->persist($b);

        $manager->flush();

        return new Response("true");
    }

    /**
     * Delete a content by POST method. This is used by ajax.
     * The response is the word true in a string in success, otherwise false.
     *
     * @route("/content/delete/{id}", name="claroline_content_delete")
     * @Secure(roles="ROLE_ADMIN")
     *
     * @param \String $id The id of the content.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function deleteAction($id)
    {
        $manager = $this->getDoctrine()->getManager();

        $content = $manager->getRepository("ClarolineCoreBundle:Home\Content")->find($id);

        $this->deleNodeEntity("ClarolineCoreBundle:Home\Content2Type", array('content' => $content));

        $this->deleNodeEntity(
            "ClarolineCoreBundle:Home\SubContent", array('father' => $content),
            function ($entity) {
                $this->deleteAction($entity->getChild()->getId());
            }
        );

        $this->deleNodeEntity("ClarolineCoreBundle:Home\SubContent", array('child' => $content));
        $this->deleNodeEntity("ClarolineCoreBundle:Home\Content2Region", array('content' => $content));

        $manager->remove($content);
        $manager->flush();

        return new Response("true");
    }

    /**
     * @route(
     *     "/region/{region}/{id}",
     *     requirements={"id" = "\d+"},
     *     name="claroline_content_to_region"
     * )
     *
     * @Secure(roles="ROLE_ADMIN")
     *
     */
    public function contentToRegionAction($region, $id)
    {
        $manager = $this->getDoctrine()->getManager();

        $content = $manager->getRepository("ClarolineCoreBundle:Home\Content")->find($id);
        $region = $manager->getRepository("ClarolineCoreBundle:Home\Region")->findOneBy(
            array("name" => $region)
        );

        $first = $manager->getRepository("ClarolineCoreBundle:Home\Content2Region")->findOneBy(
            array("back" => null, "region" => $region)
        );

        $contentRegion = new Content2Region($first);
        $contentRegion->setRegion($region);
        $contentRegion->setContent($content);

        $manager->persist($contentRegion);
        $manager->flush();

        return new Response("true");
    }

    /**
     * Render the HTML of the creator box.
     *
     * @param \String $type The type of the content to create.
     *
     * @route(
     *     "/content/creator/{type}/{id}/{father}",
     *     name="claroline_content_creator",
     *     defaults={"father" = null}
     * )
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function creatorAction($type, $id = null, $content = null, $father = null)
    {
        //cant use @Secure(roles="ROLE_ADMIN") annotation beacause this method is called in anonymous mode

        if ($this->get('security.context')->isGranted('ROLE_ADMIN')) {

            $path = $this->container->get('claroline.common.home_service')->defaultTemplate(
                "ClarolineCoreBundle:Home/types:".$type.".creator.twig"
            );

            $variables = array('type' => $type);

            if ($id and !$content) {
                $manager = $this->getDoctrine()->getManager();

                $variables["content"] = $manager->getRepository("ClarolineCoreBundle:Home\Content")->find($id);
            }

            $variables = $this->isDefinedPush($variables, "father", $father);

            return $this->render($path, $variables);
        }

        return new Response();
    }

    /**
     * Render the HTML of the menu in a content.
     *
     * @param \String $id The id of the content.
     * @param \String $size The size (span12) of the content.
     * @param \String $type The type of the content.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function menuAction($id, $size, $type, $father = null)
    {
        $variables = array('id' => $id, 'size' => $size, 'type' => $type);

        $variables = $this->isDefinedPush($variables, "father", $father, "getId");

        return $this->render('ClarolineCoreBundle:Home:menu.html.twig', $variables);
    }

    /**
     * Render the HTML of the menu of sizes of the contents.
     *
     * @param \String $id The id of the content.
     * @param \String $size The size (span12) of the content.
     * @param \String $type The type of the content.
     *
     * @route("/content/size/{id}/{size}/{type}", name="claroline_content_size")
     *
     * @Template("ClarolineCoreBundle:Home:sizes.html.twig")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function sizeAction($id, $size, $type)
    {
        return array('id' => $id, 'size' => $size, 'type' => $type);
    }

    /**
     * Render the HTML of a content generated by an external url with Open Grap meta tags
     *
     * @route("/content/graph", name="claroline_content_graph")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function graphAction()
    {
        $response = "false";
        $request = $this->get('request');

        $url = $request->get("generated_content_url");

        $graph = $this->container->get('claroline.common.graph_service')->get($url);

        if (isset($graph['type'])) {
            $response = $this->render(
                $this->container->get('claroline.common.home_service')->defaultTemplate(
                    "ClarolineCoreBundle:Home/graph:".$graph['type'].".html.twig"
                ),
                array('content' => $graph)
            )->getContent();
        }

        return new Response($response);
    }

    /**
     * Render the HTML of the regions.
     *
     * @route(
     *     "/content/region/{id}",
     *     name="claroline_region"
     * )
     *
     * @param \String $id The id of the content.
     *
     * @Template("ClarolineCoreBundle:Home:regions.html.twig")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function regionAction($id)
    {
        return array('id' => $id);
    }

    /**
     *  Reduce "overall complexity"
     *
     *  @Secure(roles="ROLE_ADMIN")
     *
     */
    private function deleNodeEntity($name, $search, $function = null)
    {
        $manager = $this->getDoctrine()->getManager();

        $entities = $manager->getRepository($name)->findBy($search);

        foreach ($entities as $entity) {
            $entity->detach();

            if ($function) {
                $function($entity);
            }

            $manager->remove($entity);
        }
    }

    /**
     *  Reduce "overall complexity"
     *
     */
    private function isDefinedPush($array, $name, $variable, $method = null)
    {
        if ($method and $variable) {
            $array[$name] = $variable->$method();
        } elseif ($variable) {
            $array[$name] = $variable;
        }

        return $array;
    }
}

