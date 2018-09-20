<?php
namespace Puzzle\Admin\BlogBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Puzzle\Admin\BlogBundle\Form\Type\CommentCreateType;

/**
 * 
 * @author AGNES Gnagne Cedric <cecenho55@gmail.com>
 *
 */
class CommentController extends Controller
{
	/***
	 * List comments
	 * 
	 * @param Request $request
	 * @return \Symfony\Component\HttpFoundation\Response
	 * @Security("has_role('ROLE_BLOG') or has_role('ROLE_ADMIN')")
	 */
    public function listAction(Request $request) {
		/** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
		$apiClient = $this->get('puzzle_connect.api_client');
		$article = null;
		if ($articleId = $request->query->get('articleId')) {
		    $article = $apiClient->pull('/blog/articles/'.$articleId);
		    $comments = $article['_embedded']['comments'] ?? null;
		}else {
		    $comments = $apiClient->pull('/blog/comments');
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
	    $parentId = $request->query->get('parent');
	    $data = ['article'  => '', 'parent' => $parentId, 'content' => ''];
	    
	    $form = $this->createForm(CommentCreateType::class, $data, [
	        'method' => 'POST',
	        'action' => !$parentId ? $this->generateUrl('admin_blog_comment_create') :
	        $this->generateUrl('admin_blog_category_create', ['parent' => $parentId])
	    ]);
	    $form->handleRequest($request);
	    
	    if ($form->isSubmitted() === true && $form->isValid() === true) {
	        $postData = $form->getData();
	        $postData['authorName'] = $this->getUser()->getFullName();
	        $postData['authorEmail'] = $this->getUser()->getEmail();
	        
	        /** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
	        $apiClient = $this->get('puzzle_connect.api_client');
	        $apiClient->push('post', '/blog/comments', $postData);
	        
	        return new JsonResponse(true);
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
	    /** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
	    $apiClient = $this->get('puzzle_connect.api_client');
	    $comment = $apiClient->pull('/blog/comments/'. $id);
	    
	    return $this->render("PuzzleAdminBlogBundle:Comment:show.html.twig",[
	        'comment' => $comment,
	        'article' => $comment['_embedded']['article']
	    ]);
	}
	
    /***
     * Approve a comment
     * 
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Security("has_role('ROLE_BLOG') or has_role('ROLE_ADMIN')")
     */
    public function approveAction(Request $request, $id) {
        /** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
        $apiClient = $this->get('puzzle_connect.api_client');
        $apiClient->push('put', '/blog/comments/'. $id, ['is_visible' => true]);
        
        return new JsonResponse(['url' => $this->generateUrl('admin_blog_comment_disapprove', ['id' => $id])]);
    }
    
    /***
     * Disapprove a comment
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Security("has_role('ROLE_BLOG') or has_role('ROLE_ADMIN')")
     */
    public function disapproveAction(Request $request, $id) {
        /** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
        $apiClient = $this->get('puzzle_connect.api_client');
        $apiClient->push('put', '/blog/comments/'. $id, ['is_visible' => false]);
        
        return new JsonResponse(['url' => $this->generateUrl('admin_blog_comment_approve', ['id' => $id])]);
    }
    
    /***
     * Remove comment
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @Security("has_role('ROLE_BLOG') or has_role('ROLE_ADMIN')")
     */
    public function deleteAction(Request $request, $id){
        /** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
        $apiClient = $this->get('puzzle_connect.api_client');
    	$response = $apiClient->push('delete', '/blog/comments/'.$id);
    	
    	return new JsonResponse(null, $response['code']);
    }
}
