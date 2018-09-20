<?php
namespace Puzzle\Admin\BlogBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Puzzle\Admin\BlogBundle\Form\Type\ArticleCreateType;
use Puzzle\Admin\BlogBundle\Form\Type\ArticleUpdateType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

/**
 * 
 * @author AGNES Gnagne Cedric <cecenho55@gmail.com>
 *
 */
class ArticleController extends Controller
{
	/***
	 * List articles
	 * 
	 * @param Request $request
	 * @return \Symfony\Component\HttpFoundation\Response
	 * @Security("has_role('ROLE_BLOG') or has_role('ROLE_ADMIN')")
	 */
    public function listAction(Request $request) {
		/** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
		$apiClient = $this->get('puzzle_connect.api_client');
		$articles = $apiClient->pull('/blog/articles');
		
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
        $data = [
            'name'  => '',
            'category' => $categoryId, 
            'description' => '',
            'enableComments' => false,
            'visible' => true,
            'tags' => ''
        ];
        
        /** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
        $apiClient = $this->get('puzzle_connect.api_client');
        $items = $apiClient->pull('/blog/categories', ['fields' => 'name,id']);
        $categories = [];
        
        foreach ($items as $item) {
            $categories[$item['name']] = $item['id'];
        }
        
        $form = $this->createForm(ArticleCreateType::class, $data, [
            'method' => 'POST',
            'action' => !$categoryId ? $this->generateUrl('admin_blog_article_create') : 
                                       $this->generateUrl('admin_blog_article_create', ['category' => $categoryId])
        ]);
        $form->add('category', ChoiceType::class, array(
            'translation_domain' => 'admin',
            'label' => 'blog.article.category',
            'label_attr' => [
                'class' => 'form-label'
            ],
            'choices' => $categories,
            'attr' => [
                'class' => 'select'
            ],
        ));
        $form->handleRequest($request);
            
        if ($form->isSubmitted() === true && $form->isValid() === true) {
            /** @var Puzzle\Admin\MediaBundle\Service\UploadManager $uploader */
            $uploader = $this->get('admin.media.upload_manager');
            $uploads = $uploader->prepareUpload($_FILES, $request->getSchemeAndHttpHost());
            
            $postData = $form->getData();
            $postData['picture'] = $uploads && count($uploads) > 0 ? $uploads[0] : $postData['file-url'] ?? null;
            $postData['tags'] = $postData['tags'] ? explode(',', $postData['tags']) : null;
            $postData['author'] = $postData['author'] ?? $this->getUser()->getFullName();
            
            /** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
            $apiClient = $this->get('puzzle_connect.api_client');
            $apiClient->push('post', '/blog/articles', $postData);
            
            if ($categoryId !== null) {
                return $this->redirectToRoute('admin_blog_category_show', array('id' => $categoryId));
            }
            
            return $this->redirectToRoute('admin_blog_article_list');
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
        /** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
        $apiClient = $this->get('puzzle_connect.api_client');
        $article = $apiClient->pull('/blog/articles/'.$id);
        $category = $article['_embedded']['category'];
        
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
        $article = $apiClient->pull('/blog/articles/'.$id);
        
        $categoryId = $request->query->get('category') ?? $article['_embedded']['category']['id'];
        $data = [
            'name'  => $article['name'],
            'category' => $categoryId,
            'description' => $article['description'],
            'enableComments' => $article['enableComments'],
            'visible' => $article['visible'],
            'tags' => isset($article['tags']) ? implode(',', $article['tags']) : ''
        ];
        
        /** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
        $apiClient = $this->get('puzzle_connect.api_client');
        $items = $apiClient->pull('/blog/categories', ['fields' => 'name,id']);
        $categories = [];
        
        foreach ($items as $item) {
            $categories[$item['name']] = $item['id'];
        }
        
        $form = $this->createForm(ArticleCreateType::class, $data, [
            'method' => 'POST',
            'action' => !$categoryId ? $this->generateUrl('admin_blog_article_update', ['id' => $id]) :
            $this->generateUrl('admin_blog_article_update', ['id' => $id, 'category' => $categoryId])
        ]);
        $form->add('category', ChoiceType::class, array(
            'translation_domain' => 'admin',
            'label' => 'blog.property.article.category',
            'label_attr' => [
                'class' => 'form-label'
            ],
            'choices' => $categories,
            'data' => $categoryId,
            'attr' => [
                'class' => 'select'
            ],
        ));
        $form->handleRequest($request);
        
        if ($form->isSubmitted() === true && $form->isValid() === true) {
            /** @var Puzzle\Admin\MediaBundle\Service\UploadManager $uploader */
            $uploader = $this->get('admin.media.upload_manager');
            $uploads = $uploader->prepareUpload($_FILES, $request->getSchemeAndHttpHost());
            
            $postData = $form->getData();
            $postData['picture'] = $uploads && count($uploads) > 0 ? $uploads[0] : $postData['file-url'] ?? null;
            $postData['tags'] = $postData['tags'] ? explode(',', $postData['tags']) : null;
            
            /** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
            $apiClient = $this->get('puzzle_connect.api_client');
            $apiClient->push('put', '/blog/articles/'.$id, $postData);
            
            return $this->redirectToRoute('admin_blog_article_update', array('id' => $id));
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
    public function deleteAction(Request $request, $id){
        /** @var Puzzle\ConectBundle\Service\PuzzleAPIClient $apiClient */
        $apiClient = $this->get('puzzle_connect.api_client');
        $article = $apiClient->pull('/blog/articles/'.$id);
        $parentId = $article['_embedded']['parent']['id'] ?? null;
        
        if ($parentId){
            $route = $this->redirectToRoute('admin_blog_article_show', array('id' => $parentId));
    	}else{
    		$route = $this->redirectToRoute('admin_blog_article_list');
    	}
    	
    	$response = $apiClient->push('delete', '/blog/articles/'.$id);
    	if ($request->isXmlHttpRequest()) {
    	    return new JsonResponse($response);
    	}
    	
    	return $route;
    }
}
