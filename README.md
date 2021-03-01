# Laravel Repositories Command

Artisan command to create Repository with RepositoryInterface for each Model and use it in the application with the dependency injection.

## Installation

Require package with composer :

```
composer require antoiner/laravel-repositories-command --dev
```

## Command

Once installed run the following command to create RepositoryInterface and Repository for each Model of your application :

```
php artisan make:repositories
```

This command also create RepositoryServiceProvider to bind all RepositoryInterface with its correspondent Repository . 

After executing the command you just have to add this line in the ```config/app.php``` to register the service provider :

```php
App\Providers\RepositoryServiceProvider::class,
```

## Usage

Now you can use Repository in your application like this  :

```php
//routes/web.php

<?php

use Illuminate\Support\Facades\Route;

Route::get('/', "HomeController@index");

```



```php
//app/Http/Controllers/HomeController.php

<?php

namespace App\Http\Controllers;

use App\Repositories\UserRepositoryInterface;

class HomeController extends Controller
{
    private $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function index() {
        dd($this->userRepository->all());
    }
}
```

## Annotations

You can **disable** the repository generation for a Model by adding the annotation ``` @Repository(enable = false)``` in the class documentation.

```php
//Blog.php

<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @Repository(enable = false)
 */
class Blog extends Model
{
    //
}

```

