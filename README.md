# Puzzle Admin Blog Bundle
**=========================**

Puzzle bundle for managing admin 

### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the following command to download the latest stable version of this bundle:

`composer require webundle/puzzle-admin-blog`

### Step 2: Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles in the `app/AppKernel.php` file of your project:

```php
<?php
// app/AppKernel.php

// ...
class AppKernel extends Kernel
{
    public function registerBundles()
{
    $bundles = array(
    // ...

    new Puzzle\Admin\BlogBundle\PuzzleAdminBlogBundle(),
                    );

 // ...
}

 // ...
}
```

### Step 3: Register the Routes

Load the bundle's routing definition in the application (usually in the `app/config/routing.yml` file):

# app/config/routing.yml
```yaml
puzzle_admin:
        resource: "@PuzzleAdminBlogBundle/Resources/config/routing.yml"
```

### Step 4: Configure Dependency Injection

Then, enable management bundle via admin modules interface by adding it to the list of registered bundles in the `app/config/config.yml` file of your project under:

```yaml
# Puzzle Client Blog
puzzle_admin_blog:
    title: blog.title
    description: blog.description
    icon: blog.icon
    roles:
        blog:
            label: 'ROLE_BLOG'
            description: blog.role.default
```

### Step 5: Configure navigation menu

Then, enable management bundle via admin modules interface by adding it to the list of registered bundles in the `app/config/config.yml` file of your project under:

```yaml
# Client Admin
puzzle_admin:
    ...
     navigation:
        nodes:
        	 ...
            blog:
                label: 'blog.base'
                translation_domain: 'admin'
                attr:
                    class: 'icon-list'
                parent: ~
                user_roles: ['ROLE_BLOG', 'ROLE_ADMIN']
                tooltip: 'blog.tooltip'
            blog_article:
                label: 'blog.article.base'
                translation_domain: 'admin'
                path: 'admin_blog_article_list'
                sub_paths: ['admin_blog_article_create', 'admin_blog_article_update', 'admin_blog_article_show']
                parent: blog
                user_roles: ['ROLE_BLOG', 'ROLE_ADMIN']
                tooltip: 'blog.article.tooltip'
            blog_category:
                label: 'blog.category.base'
                translation_domain: 'admin'
                path: 'admin_blog_category_list'
                sub_paths: ['admin_blog_category_create', 'admin_blog_category_update', 'admin_blog_category_show']
                parent: blog
                user_roles: ['ROLE_BLOG', 'ROLE_ADMIN']
                tooltip: 'blog.category.tooltip'
            blog_comment:
                label: 'blog.comment.base'
                translation_domain: 'admin'
                path: 'admin_blog_comment_list'
                parent: blog
                user_roles: ['ROLE_BLOG', 'ROLE_ADMIN']
                tooltip: 'blog.comment.tooltip'
```