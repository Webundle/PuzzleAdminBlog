<?php

namespace Puzzle\Admin\BlogBundle\Form\Type;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Puzzle\Admin\BlogBundle\Form\Model\AbstractArticleType;

/**
 * 
 * @author AGNES Gnagne Cédric <cecenho55@gmail.com>
 * 
 */
class ArticleCreateType extends AbstractArticleType
{
    public function configureOptions(OptionsResolver $resolver) {
        parent::configureOptions($resolver);
        
        $resolver->setDefault('csrf_token_id', 'article_create');
        $resolver->setDefault('validation_groups', ['Create']);
    }
    
    public function getBlockPrefix() {
        return 'admin_blog_article_create';
    }
}