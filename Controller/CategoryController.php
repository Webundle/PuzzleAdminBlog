<?php
namespace Puzzle\Admin\BlogBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Puzzle\Admin\BlogBundle\Form\Type\CategoryCreateType;
use Puzzle\Admin\BlogBundle\Form\Type\CategoryUpdateType;
use Puzzle\ConnectBundle\Service\PuzzleApiObjectManager;
use GuzzleHttp\Exception\BadResponseException;
use Puzzle\ConnectBundle\ApiEvents;
use Puzzle\ConnectBundle\Event\ApiResponseEvent;

/**
 * 
 * @author AGNES Gnagne Cedric <cecenho55@gmail.com>
 *
 */
class CategoryController extends Controller
{
    /**
     * @var array $fields
     */
    private $fields;
    
    public function __construct() {
        $this->fields = ['name', 'parent'];
    }
    
	/***
	 * List categories
	 * 
	 * @param Request $request
	 * @return \Symfony\Component\HttpFoundation\Response
	 * @Security("has_role('ROLE_BLOG') or has_role('ROLE_ADMIN')")
	 */
    public function listAction(Request $request, $current = "NULL") {
		try {
		    $criteria = [];
		    $criteria['filter'] = 'parent=='.$current;
		    
		    /** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
		    $apiClient = $this->get('puzzle_connect.api_client');
		    $categories = $apiClient->pull('/blog/categories', $criteria);
		    $currentCategory = $current != "NULL" ? $apiClient->pull('/blog/categories/'.$current) : null;
		    
		    if ($currentCategory && isset($currentCategory['_embedded']) && isset($currentCategory['_embedded']['parent'])) {
		        $parent = $currentCategory['_embedded']['parent'];
		    }else {
		        $parent = null;
		    }
		    
		}catch (BadResponseException $e) {
		    /** @var EventDispatcher $dispatcher */
		    $dispatcher = $this->get('event_dispatcher');
		    $dispatcher->dispatch(ApiEvents::API_BAD_RESPONSE, new ApiResponseEvent($e, $request));
		    $categories = $currentCategory = $parent = [];
		}
		
		return $this->render("PuzzleAdminBlogBundle:Category:list.html.twig",[
		    'categories'      => $categories,
		    'currentCategory' => $currentCategory,
		    'parent'          => $parent
		]);
	}
	
    /***
     * Create a new category
     * 
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Security("has_role('ROLE_BLOG') or has_role('ROLE_ADMIN')")
     */
    public function createAction(Request $request) {
        $parentId = $request->query->get('parent');
        $data = PuzzleApiObjectManager::hydratate($this->fields, ['parent' => $parentId]);
        
        $form = $this->createForm(CategoryCreateType::class, $data, [
            'method' => 'POST',
            'action' => !$parentId ? $this->generateUrl('admin_blog_category_create') : 
                                     $this->generateUrl('admin_blog_category_create', ['parent' => $parentId])
        ]);
        $form->handleRequest($request);
            
        if ($form->isSubmitted() === true && $form->isValid() === true) {
            /** @var Puzzle\Admin\MediaBundle\Service\UploadManager $uploader */
            $uploader = $this->get('admin.media.upload_manager');
            $uploads = $uploader->prepareUpload($_FILES);
            
            $postData = $form->getData();
            $postData['picture'] = $uploads && count($uploads) > 0 ? $uploads[0] : null;
            $postData = PuzzleApiObjectManager::sanitize($postData);
            
            try {
                /** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
                $apiClient = $this->get('puzzle_connect.api_client');
                $apiClient->push('post', '/blog/categories', $postData);
                
            }catch (BadResponseException $e) {
                /** @var EventDispatcher $dispatcher */
                $dispatcher = $this->get('event_dispatcher');
                $dispatcher->dispatch(ApiEvents::API_BAD_RESPONSE, new ApiResponseEvent($e, $request));
            }
            
            if ($parentId !== null) {
                return $this->redirectToRoute('admin_blog_category_show', array('id' => $parentId));
            }
            
            return $this->redirectToRoute('admin_blog_category_list');
        }
        
        return $this->render("PuzzleAdminBlogBundle:Category:create.html.twig", ['form' => $form->createView()]);
    }
    
    /***
     * Show category
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Security("has_role('ROLE_BLOG') or has_role('ROLE_ADMIN')")
     */
    public function showAction(Request $request, $id) {
        try {
            /** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
            $apiClient = $this->get('puzzle_connect.api_client');
            $category = $apiClient->pull('/blog/categories/'.$id);
            
            if (isset($category['files']) && count($category['files']) > 0){
                $criteria = [];
                $criteria['filter'] = 'id=:'.implode(';', $category['files']);
                
                $articles = $apiClient->pull('/blog/artilces', $criteria);
            }else {
                $articles = null;
            }
        }catch (BadResponseException $e) {
            /** @var EventDispatcher $dispatcher */
            $dispatcher = $this->get('event_dispatcher');
            $dispatcher->dispatch(ApiEvents::API_BAD_RESPONSE, new ApiResponseEvent($e, $request));
            $category = $articles = [];
        }
        
        return $this->render("PuzzleAdminBlogBundle:Category:show.html.twig", array(
            'currentCategory' => $category,
            'childs' => $category['_embedded']['childs'] ?? null,
            'articles' => $articles,
            'parent' => $category['_embedded']['parent'] ?? null,
        ));
    }
    
    /***
     * Update category
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Security("has_role('ROLE_BLOG') or has_role('ROLE_ADMIN')")
     */
    public function updateAction(Request $request, $id) {
        /** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
        $apiClient = $this->get('puzzle_connect.api_client');
        $data = [];
        try {
            $category = $apiClient->pull('/blog/categories/'.$id);
            $parentId = $category['_embedded']['parent']['id'] ?? null;
            $data = PuzzleApiObjectManager::hydratate($this->fields, [
                'name' => $category['name'], 
                'parent' => $parentId,
            ]);
            
        }catch (BadResponseException $e) {
            /** @var EventDispatcher $dispatcher */
            $dispatcher = $this->get('event_dispatcher');
            $dispatcher->dispatch(ApiEvents::API_BAD_RESPONSE, new ApiResponseEvent($e, $request));
            $category = [];
        }
        
        $form = $this->createForm(CategoryUpdateType::class, $data, [
            'method' => 'POST',
            'action' => $this->generateUrl('admin_blog_category_update', ['id' => $id])
        ]);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() === true && $form->isValid() === true) {
            /** @var Puzzle\Admin\MediaBundle\Service\UploadManager $uploader */
            $uploader = $this->get('admin.media.upload_manager');
            $uploads = $uploader->prepareUpload($_FILES);
            
            $postData = $form->getData();
            $postData['picture'] = $uploads && count($uploads) > 0 ? $uploads[0] : null;
            $postData = PuzzleApiObjectManager::sanitize($postData);
            
            try {
                /** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
                $apiClient = $this->get('puzzle_connect.api_client');
                $apiClient->push('put', '/blog/categories/'.$id, $postData);
            }catch (BadResponseException $e) {
                /** @var EventDispatcher $dispatcher */
                $dispatcher = $this->get('event_dispatcher');
                $dispatcher->dispatch(ApiEvents::API_BAD_RESPONSE, new ApiResponseEvent($e, $request));
            }
            
            if ($parentId !== null) {
                return $this->redirectToRoute('admin_blog_category_show', array('id' => $parentId));
            }
            
            return $this->redirectToRoute('admin_blog_category_list');
        }
        
        return $this->render("PuzzleAdminBlogBundle:Category:update.html.twig", [
            'category' => $category,
            'form' => $form->createView()
        ]);
    }
    
    /***
     * Remove category
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Security("has_role('ROLE_BLOG') or has_role('ROLE_ADMIN')")
     */
    public function deleteAction(Request $request, $id) {
    	try {
    	    /** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
            $apiClient = $this->get('puzzle_connect.api_client');
        	$response = $apiClient->push('delete', '/blog/categories/'.$id);
        	
        	if ($request->isXmlHttpRequest() === true) {
        	    return new JsonResponse($response);
        	}
        	
        	$this->addFlash('success', $this->get('translator')->trans('message.delete', [], 'success'));
        	return $this->redirect($this->generateUrl('admin_blog_article_list'));
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
}
