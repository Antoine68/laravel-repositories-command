<?php

namespace Antoiner\LaravelRepositoriesCommand\Commands;

use Illuminate\Console\Command;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;


class MakeRepositoriesCommand extends Command
{



    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:repositories';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create all models repositories';

    /**
     * @var Filesystem
     */
    protected $fileSystem;

    /**
     * @var string|string[]
     */
    protected $repositoriesPath;

    /**
     * @var string
     */
    protected $repositoriesNamespace;

    /**
     * @var string|string[]
     */
    protected $interfacesPath;

    /**
     * @var string
     */
    protected $interfacesNamespace;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->fileSystem = new Filesystem();

        $this->repositoriesPath = str_replace('\\', '/', app_path("\Repositories\Eloquent\\"));
        $this->repositoriesNamespace = "App\\Repositories\\Eloquent";

        $this->interfacesPath = str_replace('\\', '/',app_path("\Repositories\\"));
        $this->interfacesNamespace = "App\\Repositories";
    }

    /**
     * @param $model
     * @return string
     */
    protected function interfaceOfModelPath($model)
    {
        return $this->interfacesPath . class_basename($model) . 'RepositoryInterface.php';
    }

    /**
     * @param $model
     * @return string
     */
    protected function repositoryOfModelPath($model)
    {
        return $this->repositoriesPath. class_basename($model) . 'Repository.php';
    }

    /**
     * @param $stubName
     * @param array $values
     * @return false|string|string[]
     */
    protected function generateContent($stubName, array $values = [])
    {
        $content = file_get_contents(__DIR__ . "/stubs/" . $stubName . ".stub");

        foreach ($values as $key => $value) {
            $content = str_replace('$' . strtoupper($key) . '$', $value, $content);
        }

        return $content;
    }

    /**
     * @param $path
     * @param $content
     * @return bool|int
     */
    protected function publishFile($path, $content)
    {
        if (!$this->fileSystem->exists($path)) {
            return $this->fileSystem->put($path, $content);
        }
    }


    /**
     * @return array
     */
    protected function getAllModels()
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);
        $models = [];
        foreach ((array)data_get($composer, 'autoload.psr-4') as $namespace => $path) {
            $models = array_merge(collect(File::allFiles(base_path($path)))
                ->map(function ($item) use ($namespace) {
                    $path = $item->getRelativePathName();
                    return sprintf('\%s%s',
                        $namespace,
                        strtr(substr($path, 0, strrpos($path, '.')), '/', '\\'));
                })
                ->filter(function ($class) {
                    return class_exists($class) && $this->filterRepository($class);
                })
                ->values()
                ->toArray(), $models);
        }
        return $models;
    }


    /**
     * @param $class
     * @return bool
     */
    protected function filterRepository($class)
    {
        try {
            $reflection = new \ReflectionClass($class);
        } catch (\ReflectionException $e) {
            throw new $e;
        }
        $repositoryAnnotation = $this->getRepositoryAnnotation($reflection);
        if (!$repositoryAnnotation) $this->info($class . " -> ignored");
        return $reflection->isSubclassOf(\Illuminate\Database\Eloquent\Model::class) &&
            !$reflection->isAbstract() && $repositoryAnnotation;
    }

    /**
     * @param $reflection
     * @return bool
     */
    protected function getRepositoryAnnotation($reflection)
    {
        $doc = $reflection->getDocComment();
        preg_match_all('#@Repository\(( *?)enable( *?)=( *?)(.*?)( *?)\)\n#s', $doc, $annotations);
        $enableAnnotation = ($annotations[4][0] ?? null);
        if ($enableAnnotation) {
            $enableAnnotation = trim($enableAnnotation);
            return ($enableAnnotation == "true" || $enableAnnotation == "1");
        }

        return true;
    }


    protected function createDirectories()
    {
        if (!is_dir($this->repositoriesPath)) {
            $this->laravel['files']->makeDirectory($this->repositoriesPath, 0777, true);
        }

        if (!is_dir($this->interfacesPath)) {
            $this->laravel['files']->makeDirectory($this->interfacesPath, 0777, true);
        }
    }

    protected function removeDirectories()
    {
        if (is_dir($this->repositoriesPath)) {
            $this->laravel['files']->deleteDirectory($this->repositoriesPath);
        }

        if (is_dir($this->interfacesPath)) {
            $this->laravel['files']->deleteDirectory($this->interfacesPath);
        }
    }


    protected function createBases()
    {
        $eloquentRepositoryInterfaceContent = $this->generateContent("eloquent-repository-interface");
        $this->publishFile($this->interfacesPath . "/EloquentRepositoryInterface.php", $eloquentRepositoryInterfaceContent);

        $modelRepositoryContent = $this->generateContent("model-repository");
        $this->publishFile($this->repositoriesPath . "/ModelRepository.php", $modelRepositoryContent);

    }

    /**
     * @param $model
     * @throws \ReflectionException
     */
    protected function manageModel($model)
    {
        $interfaceModelPath = $this->interfaceOfModelPath($model);
        $interfaceBasename = pathinfo(class_basename($interfaceModelPath), PATHINFO_FILENAME);

        $repositoryModelPath = $this->repositoryOfModelPath($model);
        $repositoryBasename = pathinfo(class_basename($repositoryModelPath), PATHINFO_FILENAME);

        $modelBasename = class_basename($model);
        $modelNamespace = (new \ReflectionClass($model))->getNamespaceName();

        $interfaceModelContent = $this->generateContent("repository-interface", [
            "CLASS_NAMESPACE" => $this->interfacesNamespace,
            "MODEL_NAMESPACE" => $modelNamespace . "\\" . $modelBasename,
            "INTERFACE" => $interfaceBasename,
            "MODEL" => $modelBasename
        ]);
        $this->publishFile($interfaceModelPath, $interfaceModelContent);


        $repositoryModelContent = $this->generateContent("repository", [
            "CLASS_NAMESPACE" => $this->repositoriesNamespace,
            "INTERFACE_NAMESPACE" => $this->interfacesNamespace . "\\" . $interfaceBasename,
            "MODEL_NAMESPACE" => $modelNamespace . "\\" . $modelBasename,
            "CLASS" => $repositoryBasename,
            "INTERFACE" => $interfaceBasename,
            "MODEL" => $modelBasename
        ]);
        $this->publishFile($repositoryModelPath, $repositoryModelContent);


    }

    /**
     * @param $models
     */
    protected function createServiceProvider($models)
    {

        $repositoryServiceProviderPath =
            (str_replace('\\', '/', app_path("\Providers\\")) . "RepositoryServiceProvider.php");

        if (file_exists($repositoryServiceProviderPath)) {
            $this->laravel['files']->delete($repositoryServiceProviderPath);
        }

        $data = array("repositories_namespaces" => array(), "interfaces_namespaces" => array(), "binds" => array());

        foreach ($models as $model) {
            $interfaceModelPath = $this->interfaceOfModelPath($model);
            $interfaceBasename = pathinfo(class_basename($interfaceModelPath), PATHINFO_FILENAME);

            $repositoryModelPath = $this->repositoryOfModelPath($model);
            $repositoryBasename = pathinfo(class_basename($repositoryModelPath), PATHINFO_FILENAME);

            array_push($data["repositories_namespaces"], ("use " . $this->repositoriesNamespace . "\\" . $repositoryBasename . ";"));
            array_push($data["interfaces_namespaces"], ("use " . $this->interfacesNamespace . "\\" . $interfaceBasename . ";"));
            array_push($data["binds"],
                ("\$this->app->bind(" .
                    $interfaceBasename . "::class, " .
                    $repositoryBasename . "::class);"
                ));
        }


        $repositoryProviderContent = $this->generateContent("repository-provider", [
            "REPOSITORIES_NAMESPACES" => implode("\n", $data["repositories_namespaces"]),
            "INTERFACES_NAMESPACES" => implode("\n", $data["interfaces_namespaces"]),
            "BINDS" => implode("\n        ", $data["binds"])
        ]);

        $this->publishFile($repositoryServiceProviderPath, $repositoryProviderContent);

    }


    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            //$this->removeDirectories();
            $this->createDirectories();
            $this->createBases();
            $models = $this->getAllModels();
            foreach ($models as $model) {
                $this->manageModel($model);
                $this->info($model . " -> success (repository and interface)");
            }
            $this->createServiceProvider($models);
            $this->info("\App\Providers\RepositoryServiceProvider -> success");
        } catch (\Exception $e) {
            $this->error("File : {$e->getMessage()}");

            return E_ERROR;
        }

        $this->info("repositories generation successfully completed");

        return 0;
    }
}
