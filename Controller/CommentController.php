<?php
namespace Puzzle\Admin\BlogBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Puzzle\Admin\BlogBundle\Form\Type\CommentCreateType;
use GuzzleHttp\Exception\BadResponseException;
use Puzzle\ConnectBundle\ApiEvents;
use Puzzle\ConnectBundle\Event\ApiResponseEvent;
use Puzzle\ConnectBundle\Service\PuzzleApiObjectManager;

/**
 * 
 * @author AGNES Gnagne Cedric <cecenho55@gmail.com>
 *
 */
class CommentController extends Controller
{
    /**
     * @var array $fields
     */
    private $fields;
    
    public function __construct() {
        $this->fields = ['parent', 'article', 'content'];
    }
    
	/***
	 * List comments
	 * 
	 * @param Request $request
	 * @return \Symfony\Component\HttpFoundation\Response
	 * @Security("has_role('ROLE_BLOG') or has_role('ROLE_ADMIN')")
	 */
    public function listAction(Request $request) {
		try {
		    /** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
    		$apiClient = $this->get('puzzle_connect.api_client');
    		$article = null;
    		if ($articleId = $request->query->get('articleId')) {
    		    $article = $apiClient->pull('/blog/articles/'.$articleId);
    		    $comments = $article['_embedded']['comments'] ?? null;
    		}else {
    		    $comments = $apiClient->pull('/blog/comments');
    		}
		}catch (BadResponseException $e) {
		    /** @var EventDispatcher $dispatcher */
		    $dispatcher = $this->get('event_dispatcher');
		    $dispatcher->dispatch(ApiEvents::API_BAD_RESPONSE, new ApiResponseEvent($e, $request));
		    $comments = $article = [];
		}
		
		return $this->render("PuzzleAdminBlogBundle:Comment:list.html.twig",[
		    'article' => $article,
		    'comments' => $comments
		]);
	}
	
	/***
	 * Create a new comment
	 *
	 * @param Request $request
	 * @return \Symfony\Component\HttpFoundation\Response
	 * @Security("has_role('ROLE_BLOG') or has_role('ROLE_ADMIN')")
	 */
	public function createAction(Request $request) {
	    $articleId = $request->query->get('article');
	    $parentId = $request->query->get('parent');
	    $data = PuzzleApiObjectManager::hydratate($this->fields, [
	        'parent' => $parentId,
	        'article' => $articleId
	    ]);
	    $form = $this->createForm(CommentCreateType::class, $data, [
	        'method' => 'POST',
	        'action' => !$parentId ? $this->generateUrl('admin_blog_comment_create') :
	        $this->generateUrl('admin_blog_comment_create', ['parent' => $parentId])
	    ]);
	    $form->handleRequest($request);
	    
	    if ($form->isSubmitted() === true && $form->isValid() === true) {
	        $postData = $form->getData();
	        $postData['authorName'] = $this->getUser()->getFullName();
	        $postData['authorEmail'] = $this->getUser()->getEmail();
	        $postData = PuzzleApiObjectManager::sanitize($postData);
	        
	        try {
	            
	            /** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
	            $apiClient = $this->get('puzzle_connect.api_client');
	            $apiClient->push('post', '/blog/comments', $postData);
	            
	            if ($request->isXmlHttpRequest() === true) {
	                return new JsonResponse(true);
	            }
	            
	            $this->addFlash('success', $this->get('translator')->trans('message.post', [], 'success'));
	            
	            return $this->redirectToRoute('admin_blog_article', array('id' => $articleId));
	        }catch (BadResponseException $e) {
	            /** @var EventDispatcher $dispatcher */
	            $dispatcher = $this->get('event_dispatcher');
	            $event = $dispatcher->dispatch(ApiEvents::API_BAD_RESPONSE, new ApiResponseEvent($e, $request));
	            
	            if ($request->isXmlHttpRequest() === true) {
	                return $event->getResponse();
	            }
	            
	            return $this->redirectToRoute('admin_blog_article', array('id' => $articleId));
	        }
	    }
	    
	    return $this->render("PuzzleAdminBlogBundle:Comment:create.html.twig", ['form' => $form->createView()]);
	}
	
	/***
	 * Show a comment
	 *
	 * @param Request $request
	 * @return \Symfony\Component\HttpFoundation\Response
	 * @Security("has_role('ROLE_BLOG') or has_role('ROLE_ADMIN')")
	 */
	public function showAction(Request $request, $id) {
	    try {
	        /** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
	        $apiClient = $this->get('puzzle_connect.api_client');
	        $comment = $apiClient->pull('/blog/comments/'. $id);
	        
	        return $this->render("PuzzleAdminBlogBundle:Comment:show.html.twig",[
	            'comment' => $comment
	        ]);
	    }catch (BadResponseException $e) {
	        /** @var EventDispatcher $dispatcher */
	        $dispatcher = $this->get('event_dispatcher');
	        $event  = $dispatcher->dispatch(ApiEvents::API_BAD_RESPONSE, new ApiResponseEvent($e, $request));
	        
	        return $event->getResponse();
	    }
	}
	
    /***
     * Approve a comment
     * 
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Security("has_role('ROLE_BLOG') or has_role('ROLE_ADMIN')")
     */
    public function approveAction(Request $request, $id) {
        try {
            /** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
            $apiClient = $this->get('puzzle_connect.api_client');
            $apiClient->push('put', '/blog/comments/'. $id, ['is_visible' => true]);
            
            return new JsonResponse(['url' => $this->generateUrl('admin_blog_comment_disapprove', ['id' => $id])]);
        }catch (BadResponseException $e) {
            /** @var EventDispatcher $dispatcher */
            $dispatcher = $this->get('event_dispatcher');
            $event  = $dispatcher->dispatch(ApiEvents::API_BAD_RESPONSE, new ApiResponseEvent($e, $request));
            
            return $event->getResponse();
        }
    }
    
    /***
     * Disapprove a comment
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Security("has_role('ROLE_BLOG') or has_role('ROLE_ADMIN')")
     */
    public function disapproveAction(Request $request, $id) {
        try {
            /** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
            $apiClient = $this->get('puzzle_connect.api_client');
            $apiClient->push('put', '/blog/comments/'. $id, ['is_visible' => false]);
            
            return new JsonResponse(['url' => $this->generateUrl('admin_blog_comment_approve', ['id' => $id])]);
        }catch (BadResponseException $e) {
            /** @var EventDispatcher $dispatcher */
            $dispatcher = $this->get('event_dispatcher');
            $event  = $dispatcher->dispatch(ApiEvents::API_BAD_RESPONSE, new ApiResponseEvent($e, $request));
            
            return $event->getResponse();
        }
    }
    
    /***
     * Remove comment
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Security("has_role('ROLE_BLOG') or has_role('ROLE_ADMIN')")
     */
    public function deleteAction(Request $request, $id) {
        try {
            /** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
            $apiClient = $this->get('puzzle_connect.api_client');
        	$response = $apiClient->push('delete', '/blog/comments/'.$id);
        	
        	return new JsonResponse(null, $response['code']);
        }catch (BadResponseException $e) {
            /** @var EventDispatcher $dispatcher */
            $dispatcher = $this->get('event_dispatcher');
            $event  = $dispatcher->dispatch(ApiEvents::API_BAD_RESPONSE, new ApiResponseEvent($e, $request));
            
            return $event->getResponse();
        }
    }
}
