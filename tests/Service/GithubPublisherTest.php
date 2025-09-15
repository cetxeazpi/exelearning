<?php

namespace App\Tests\Service;

use App\Service\GithubClientFactory;
use App\Service\GithubPublisher;
use App\Service\PagesEnabler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class GithubPublisherTest extends TestCase
{
    private function makeTempTree(): string
    {
        $dir = sys_get_temp_dir().'/ghpub_'.bin2hex(random_bytes(4)).'/';
        mkdir($dir);
        file_put_contents($dir.'index.html', '<!doctype html>');
        return $dir;
    }

    public function testPublishTreeCreatesNoJekyllAndSingleCommit(): void
    {
        $local = $this->makeTempTree();

        // Fake minimal Github client via anonymous classes
        $fake = new class {
            public array $blobs = [];
            public function getHttpClient() { return new class { public function setOption($k,$v){} }; }
            public function authenticate($t,$p,$m){}
            public function api($name) {
                $self = $this;
                return match($name) {
                    'repo' => new class {
                        public function show($o,$r){ return ['default_branch'=>'main']; }
                    },
                    'gitData' => new class($self) {
                        private $root;
                        public function __construct($r){$this->root=$r;}
                        public function references(){
                            $root = $this->root;
                            return new class($root) {
                                public function __construct($r){$this->root=$r;}
                                public function show($o,$r,$h){ throw new \RuntimeException('missing'); }
                                public function create($o,$r,$arr){ return ['object'=>['sha'=>'base_sha']]; }
                                public function update($o,$r,$ref,$data){ return ['ok'=>true]; }
                            };
                        }
                        public function blobs(){
                            $root = $this->root;
                            return new class($root) {
                                public function __construct($r){$this->root=$r;}
                                public function create($o,$r,$arr){ return ['sha'=>sha1($arr['content'])]; }
                            };
                        }
                        public function trees(){
                            return new class {
                                public function create($o,$r,$arr){ return ['sha'=>'tree_sha']; }
                            };
                        }
                        public function commits(){
                            return new class {
                                public function create($o,$r,$arr){ return ['sha'=>'commit_sha']; }
                            };
                        }
                    },
                    default => throw new \RuntimeException('Unknown api')
                };
            }
        };

        $factory = new class($fake) extends GithubClientFactory {
            private $fake;
            public function __construct($fake){ $this->fake = $fake; }
            public function createAuthenticatedClient(string $accessToken): object { return $this->fake; }
        };
        $enabler = new PagesEnabler(new MockHttpClient([new MockResponse('', ['http_code'=>201])]), 'https://api.github.com');
        $publisher = new GithubPublisher($factory, $enabler, 'gh-pages');

        $sha = $publisher->publishTree('token', 'owner', 'repo', 'gh-pages', $local, 'Test commit');
        $this->assertSame('commit_sha', $sha);
        $this->assertFileExists($local.'.nojekyll');
    }
}

