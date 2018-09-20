<?php

namespace Puzzle\Admin\BlogBundle\Form\Type;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Puzzle\Admin\BlogBundle\Form\Model\AbstractCategoryType;

/**
 *
 * @author AGNES Gnagne Cédric <cecenho55@gmail.com>
 *
 */
class CategoryUpdateType extends AbstractCategoryType
{
    public function configureOptions(OptionsResolver $resolver) {
        parent::configureOptions($resolver);
        
        $resolver->setDefault('csrf_token_id', 'category_update');
        $resolver->setDefault('validation_groups', ['Update']);
    }
    
    public function getBlockPrefix() {
        return 'admin_blog_category_updated';
    }
}