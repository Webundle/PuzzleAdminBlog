<?php
namespace Puzzle\Admin\BlogBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Puzzle\Admin\BlogBundle\Form\Type\CategoryCreateType;
use Puzzle\Admin\BlogBundle\Form\Type\CategoryUpdateType;

/**
 * 
 * @author AGNES Gnagne Cedric <cecenho55@gmail.com>
 *
 */
class CategoryController extends Controller
{
	/***
	 * List categories
	 * 
	 * @param Request $request
	 * @return \Symfony\Component\HttpFoundation\Response
	 * @Security("has_role('ROLE_BLOG') or has_role('ROLE_ADMIN')")
	 */
    public function listAction(Request $request, $current = "NULL"){
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
        $data = ['name'  => '', 'parent' => $parentId, 'slug' => ''];
        
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
            
            /** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
            $apiClient = $this->get('puzzle_connect.api_client');
            $apiClient->push('post', '/blog/categories', $postData);
            
            if ($parentId !== null) {
                return $this->redirectToRoute('admin_blog_category_show', array('id' => $parentId));
            }
            
            return $this->redirectToRoute('admin_blog_article_list');
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
        $category = $apiClient->pull('/blog/categories/'.$id);
        
        $parentId = $category['_embedded']['parent']['id'] ?? null;
        $data = ['name' => $category['name'], 'parent' => $parentId, 'slug' => $category['slug'] ?? null];
        
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
            
            /** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
            $apiClient = $this->get('puzzle_connect.api_client');
            $apiClient->push('put', '/blog/categories/'.$category['id'], $postData);
            
            if ($parentId !== null) {
                return $this->redirectToRoute('admin_blog_category_show', array('id' => $parentId));
            }
            
            return $this->redirectToRoute('admin_blog_article_list');
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
    public function deleteAction(Request $request, $id){
        /** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
        $apiClient = $this->get('puzzle_connect.api_client');
        $category = $apiClient->pull('/blog/categories/'.$id);
        $parentId = $category['_embedded']['parent']['id'] ?? null;
        
        if ($parentId){
            $route = $this->redirectToRoute('admin_blog_category_show', array('id' => $parentId));
    	}else{
    		$route = $this->redirectToRoute('admin_blog_category_list');
    	}
    	
    	$response = $apiClient->push('delete', '/blog/categories/'.$id);
    	if ($request->isXmlHttpRequest()) {
    	    return new JsonResponse($response);
    	}
    	
    	return $route;
    }
}
