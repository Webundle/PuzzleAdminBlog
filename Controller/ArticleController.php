<?php
namespace Puzzle\Admin\BlogBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Puzzle\Admin\BlogBundle\Form\Type\ArticleCreateType;
use Puzzle\Admin\BlogBundle\Form\Type\ArticleUpdateType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use GuzzleHttp\Exception\BadResponseException;
use Puzzle\ConnectBundle\ApiEvents;
use Puzzle\ConnectBundle\Event\ApiResponseEvent;
use Puzzle\ConnectBundle\Service\PuzzleApiObjectManager;
use Puzzle\ConnectBundle\Service\ErrorFactory;

/**
 * 
 * @author AGNES Gnagne Cedric <cecenho55@gmail.com>
 *
 */
class ArticleController extends Controller
{
    /**
     * @var array $fields
     */
    private $fields;
    
    public function __construct() {
        $this->fields = ['name', 'category', 'description', 'enableComments', 'visible', 'tags'];
    }
    
	/***
	 * List articles
	 * 
	 * @param Request $request
	 * @return \Symfony\Component\HttpFoundation\Response
	 * @Security("has_role('ROLE_BLOG') or has_role('ROLE_ADMIN')")
	 */
    public function listAction(Request $request) {
		try {
		   /** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
		  $apiClient = $this->get('puzzle_connect.api_client');
		  $articles = $apiClient->pull('/blog/articles');
		}catch (BadResponseException $e) {
		    /** @var EventDispatcher $dispatcher */
		    $dispatcher = $this->get('event_dispatcher');
		    $dispatcher->dispatch(ApiEvents::API_BAD_RESPONSE, new ApiResponseEvent($e, $request));
		    $articles = [];
		}
		
		return $this->render("PuzzleAdminBlogBundle:Article:list.html.twig",['articles' => $articles]);
	}
	
    /***
     * Create a new article
     * 
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Security("has_role('ROLE_BLOG') or has_role('ROLE_ADMIN')")
     */
    public function createAction(Request $request) {
        $categoryId = $request->query->get('category');
        
        $data = PuzzleApiObjectManager::hydratate($this->fields, [
            'category' => $categoryId,
            'enableComments' => false,
            'visible' => true
        ]);
        
        /** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
        $apiClient = $this->get('puzzle_connect.api_client');
        
        $form = $this->createForm(ArticleCreateType::class, $data, [
            'method' => 'POST',
            'action' => !$categoryId ? $this->generateUrl('admin_blog_article_create') : 
                                       $this->generateUrl('admin_blog_article_create', ['category' => $categoryId])
        ]);
        $form = $this->addFormPart($request, $form, $data);
        $form->handleRequest($request);
            
        if ($form->isSubmitted() === true && $form->isValid() === true) {
            /** @var Puzzle\Admin\MediaBundle\Service\UploadManager $uploader */
            $uploader = $this->get('admin.media.upload_manager');
            $uploads = $uploader->prepareUpload($_FILES, $request->getSchemeAndHttpHost());
            
            $postData = $form->getData();
            $postData['picture'] = $uploads && count($uploads) > 0 ? $uploads[0] : $postData['file-url'] ?? null;
            $postData['tags'] = $postData['tags'] ? explode(',', $postData['tags']) : null;
            $postData['author'] = $postData['author'] ?? $this->getUser()->getFullName();
            $postData = PuzzleApiObjectManager::sanitize($postData);
            
            try {
                $article = $apiClient->push('post', '/blog/articles', $postData);
                $this->addFlash('success', $this->get('translator')->trans('message.post', [], 'success'));
                
                return $this->redirectToRoute('admin_blog_article_update', array('id' => $article['id']));
            }catch (BadResponseException $e) {
                $form = ErrorFactory::createFormError($form, $e);
            }
        }
        
        return $this->render("PuzzleAdminBlogBundle:Article:create.html.twig", ['form' => $form->createView()]);
    }
    
    /***
     * Show article
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Security("has_role('ROLE_BLOG') or has_role('ROLE_ADMIN')")
     */
    public function showAction(Request $request, $id) {
        try {
            /** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
            $apiClient = $this->get('puzzle_connect.api_client');
            $article = $apiClient->pull('/blog/articles/'.$id);
            $category = $article['_embedded']['category'];
        }catch (BadResponseException $e) {
            /** @var EventDispatcher $dispatcher */
            $dispatcher = $this->get('event_dispatcher');
            $dispatcher->dispatch(ApiEvents::API_BAD_RESPONSE, new ApiResponseEvent($e, $request));
            $article = $category = [];
        }
        
        return $this->render("PuzzleAdminBlogBundle:Article:show.html.twig", array(
            'article' => $article,
            'category' => $category
        ));
    }
    
    /***
     * Update article
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Security("has_role('ROLE_BLOG') or has_role('ROLE_ADMIN')")
     */
    public function updateAction(Request $request, $id) {
        /** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
        $apiClient = $this->get('puzzle_connect.api_client');
        try {
            $article = $apiClient->pull('/blog/articles/'.$id);
        }catch (BadResponseException $e) {
            /** @var EventDispatcher $dispatcher */
            $dispatcher = $this->get('event_dispatcher');
            $dispatcher->dispatch(ApiEvents::API_BAD_RESPONSE, new ApiResponseEvent($e, $request));
            $article = [];
        }
        
        $categoryId = $request->query->get('category') ?? $article['_embedded']['category']['id'];
        
        $data = PuzzleApiObjectManager::hydratate($this->fields, [
            'name'  => $article['name'],
            'category' => $categoryId,
            'description' => $article['description'],
            'enableComments' => $article['enableComments'],
            'visible' => $article['visible'],
            'tags' => isset($article['tags']) ? implode(',', $article['tags']) : ''
        ]);
        
        $form = $this->createForm(ArticleCreateType::class, $data, [
            'method' => 'POST',
            'action' => !$categoryId ? $this->generateUrl('admin_blog_article_update', ['id' => $id]) :
            $this->generateUrl('admin_blog_article_update', ['id' => $id, 'category' => $categoryId])
        ]);
        $form = $this->addFormPart($request, $form, $data);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() === true && $form->isValid() === true) {
            /** @var Puzzle\Admin\MediaBundle\Service\UploadManager $uploader */
            $uploader = $this->get('admin.media.upload_manager');
            $uploads = $uploader->prepareUpload($_FILES, $request->getSchemeAndHttpHost());
            
            $postData = $form->getData();
            $postData['picture'] = $uploads && count($uploads) > 0 ? $uploads[0] : $postData['file-url'] ?? null;
            $postData['tags'] = $postData['tags'] ? explode(',', $postData['tags']) : null;
            $postData = PuzzleApiObjectManager::sanitize($postData);
            
            try {
                $apiClient->push('put', '/blog/articles/'.$id, $postData);
                $this->addFlash('success', $this->get('translator')->trans('message.put', [], 'success'));
                
                return $this->redirectToRoute('admin_blog_article_update', array('id' => $id));
            }catch (BadResponseException $e) {
                $form = ErrorFactory::createFormError($form, $e);
            }
        }
        
        return $this->render("PuzzleAdminBlogBundle:Article:update.html.twig", [
            'article' => $article,
            'form' => $form->createView()
        ]);
    }
    
    /***
     * Remove article
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Security("has_role('ROLE_BLOG') or has_role('ROLE_ADMIN')")
     */
    public function deleteAction(Request $request, $id) {
    	try {
    	    /** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
    	    $apiClient = $this->get('puzzle_connect.api_client');
    	    $article = $apiClient->pull('/blog/articles/'.$id);
    	    $parentId = $article['_embedded']['parent']['id'] ?? null;
    	    
    	    if ($parentId) {
    	        $route = $this->redirectToRoute('admin_blog_article_show', array('id' => $parentId));
    	    }else{
    	        $route = $this->redirectToRoute('admin_blog_article_list');
    	    }
    	    
    	    $response = $apiClient->push('delete', '/blog/articles/'.$id);
    	    if ($request->isXmlHttpRequest()) {
    	        return new JsonResponse($response);
    	    }
    	    
    	    $this->addFlash('success', $this->get('translator')->trans('message.delete', [], 'success'));
    	    
    	    return $route;
    	}catch (BadResponseException $e) {
    	    /** @var EventDispatcher $dispatcher */
    	    $dispatcher = $this->get('event_dispatcher');
    	    $event = $dispatcher->dispatch(ApiEvents::API_BAD_RESPONSE, new ApiResponseEvent($e, $request));
    	    
    	    if ($request->isXmlHttpRequest() === true) {
    	        return $event->getResponse();
    	    }
    	    
    	    return $this->redirect($this->generateUrl('admin_blog_article_list'));
    	}
    }
    
    public function addFormPart($request, $form, $data) {
        $categories = [];
        /** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
        $apiClient = $this->get('puzzle_connect.api_client');
        
        try {
            $items = $apiClient->pull('/blog/categories', ['fields' => 'name,id']);
            foreach ($items as $item) {
                $categories[$item['name']] = $item['id'];
            }
        }catch (BadResponseException $e) {
            /** @var EventDispatcher $dispatcher */
            $dispatcher = $this->get('event_dispatcher');
            $dispatcher->dispatch(ApiEvents::API_BAD_RESPONSE, new ApiResponseEvent($e, $request));
        }
        
        $form->add('category', ChoiceType::class, array(
            'translation_domain' => 'admin',
            'label' => 'blog.property.article.category',
            'label_attr' => ['class' => 'form-label'],
            'choices' => $categories,
            'data' => $data['category'],
            'attr' => ['class' => 'select'],
        ));
        
        return $form;
    }
}
