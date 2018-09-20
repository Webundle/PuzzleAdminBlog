<?php

namespace Puzzle\Admin\BlogBundle\Form\Type;

use Puzzle\Admin\BlogBundle\Form\Model\AbstractCommentType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * 
 * @author AGNES Gnagne Cédric <cecenho55@gmail.com>
 * 
 */
class CommentCreateType extends AbstractCommentType
{
    public function configureOptions(OptionsResolver $resolver) {
        parent::configureOptions($resolver);
        
        $resolver->setDefault('csrf_token_id', 'comment_create');
        $resolver->setDefault('validation_groups', ['Create']);
    }
    
    public function getBlockPrefix() {
        return 'admin_blog_comment_create';
    }
}