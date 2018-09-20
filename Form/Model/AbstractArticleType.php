<?php

namespace Puzzle\Admin\BlogBundle\Form\Model;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

/**
 * @author AGNES Gnagne Cédric <cecenho55@gmail.com>
 */
class AbstractArticleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options){
        $builder
            ->add('name', TextType::class, [
                'translation_domain' => 'admin',
                'label' => 'blog.article.name',
                'label_attr' => [
                    'class' => 'form-label'
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder'   => 'blog.article.name'
                ],
            ])
            ->add('description', TextareaType::class, array(
                'translation_domain' => 'admin',
                'label' => 'blog.article.description',
                'attr' => [
                    'class' => 'form-control summernote',
                    'title' => ""
                ],
                'required' => false
            ))
            ->add('enableComments', CheckboxType::class, array(
                'translation_domain' => 'admin',
                'label' => 'blog.article.enableComments',
                'attr' => [
                    'class' => 'switchery'
                ],
                'label_attr' => [
                    'class' => 'form-label'
                ],
                'required' => false
            ))
            ->add('visible', CheckboxType::class, array(
                'translation_domain' => 'admin',
                'attr' => [
                    'class' => 'switchery'
                ],
                'label_attr' => [
                    'class' => 'form-label'
                ],
                'required' => false
            ))
            ->add('tags', TextType::class, array(
                'translation_domain' => 'admin',
                'label' => 'blog.article.tags',
                'label_attr' => [
                    'class' => 'form-label'
                ],
                'attr' => [
                    'class' => "tokenfield"
                ],
                'required' => false
            ))
        ;
    }
}