<?php

/* this file is part of pipelines */

namespace Ktomk\Pipelines\Runner;

use Ktomk\Pipelines\Cli\Args;
use Ktomk\Pipelines\Cli\Args\ArgsTester;
use Ktomk\Pipelines\TestCase;

/**
 * @covers \Ktomk\Pipelines\Runner\Env
 */
class EnvTest extends TestCase
{
    public function testCreation()
    {
        $env = new Env();
        $this->assertInstanceOf('Ktomk\Pipelines\Runner\Env', $env);
        $this->assertNotNull($env->getArgs('-e'));
    }

    public function testStaticCreation()
    {
        $env = Env::create();
        $this->assertInstanceOf('Ktomk\Pipelines\Runner\Env', $env);
        $this->assertNotNull($env->getArgs('-e'));
    }

    public function testStaticCreationEx()
    {
        $env = Env::createEx();
        $this->assertInstanceOf('Ktomk\Pipelines\Runner\Env', $env);
        $this->assertNotNull($env->getArgs('-e'));
    }

    public function testDefaultEnvsEmptyUnlessInitialized()
    {
        $env = new Env();
        $array = $env->getArgs('-e');
        self::assertIsArray($array);
        $this->assertCount(0, $array);

        $env->initDefaultVars(array());
        $array = $env->getArgs('-e');
        $this->assertCount(12, $array);
    }

    public function testDefaultValue()
    {
        $slug = 'pipelines';
        $env = Env::createEx(array('BITBUCKET_REPO_SLUG' => $slug));
        $this->assertSame($slug, $env->getValue('BITBUCKET_REPO_SLUG'));
    }

    public function testUserInheritance()
    {
        $user = 'adele';
        $env = Env::createEx(array('USER' => $user));
        $this->assertNull($env->getValue('USER'));
        $this->assertSame($user, $env->getValue('BITBUCKET_REPO_OWNER'));
    }

    public function testInheritOnInit()
    {
        $env = new Env();
        $env->initDefaultVars(array('BITBUCKET_BUILD_NUMBER' => '123'));
        $array = $env->getArgs('-e');
        $this->assertContains('BITBUCKET_BUILD_NUMBER=123', $array);
    }

    public function testGetOptionArgs()
    {
        $env = Env::createEx();
        $args = $env->getArgs('-e');
        $this->assertIsArray($args);
        while ($args) {
            $argument = array_pop($args);
            $this->assertIsString($argument);
            $this->assertGreaterThan(0, strpos($argument, '='), 'must be a variable definition');
            $this->assertGreaterThan(0, count($args));
            $option = array_pop($args);
            $this->assertSame('-e', $option);
        }
    }

    public function testUnsetVariables()
    {
        $env = new Env();
        $env->initDefaultVars(array());
        # start count has some vars unset
        $default = count($env->getArgs('-e'));

        $env->initDefaultVars(array('BITBUCKET_BRANCH' => 'test'));
        # start count has some vars unset
        $new = count($env->getArgs('-e'));
        $this->assertSame($default + 2, $new);
    }

    public function testAddRefType()
    {
        $env = Env::createEx();
        $default = count($env->getArgs('-e'));

        $env->addReference(Reference::create());
        $this->assertCount($default, $env->getArgs('-e'), 'null reference does not add any variables');

        $env->addReference(Reference::create('branch:testing'));
        $this->assertCount($default + 2, $env->getArgs('-e'), 'full reference does add variables');
    }

    public function testPullRequestAddsBranchName()
    {
        $env = Env::createEx();
        $default = count($env->getArgs('-e'));

        $env->addReference(Reference::create());
        $this->assertCount($default, $env->getArgs('-e'), 'null reference does not add any variables');

        $env->addReference(Reference::create('pr:feature'));
        $this->assertCount($default + 2, $env->getArgs('-e'), 'full reference does add variables');

        $env->addReference(Reference::create('pr:topic:master'));
        $this->assertCount($default + 4, $env->getArgs('-e'), 'pr destination does add variables');
    }

    public function testAddRefTypeIfSet()
    {
        $env = Env::createEx(array('BITBUCKET_TAG' => 'inherit'));
        $default = count($env->getArgs('-e'));

        $env->addReference(Reference::create('tag:testing'));
        $actual = $env->getArgs('-e');
        $this->assertCount($default, $actual);

        $this->assertContains('BITBUCKET_TAG=inherit', $actual);
    }

    public function testSetContainerName()
    {
        $env = Env::createEx();
        $count = count($env->getArgs('-e'));

        $env->setContainerName('blue-seldom');
        $args = $env->getArgs('-e');
        $this->assertCount($count + 2, $args);
        $this->assertContains('PIPELINES_CONTAINER_NAME=blue-seldom', $args);

        $env->setContainerName('solar-bottom');
        $args = $env->getArgs('-e');
        $this->assertCount($count + 4, $args);
        $this->assertContains('PIPELINES_PARENT_CONTAINER_NAME=blue-seldom', $args);
        $this->assertContains('PIPELINES_CONTAINER_NAME=solar-bottom', $args);
    }

    public function testInheritedContainerName()
    {
        $inherit = array(
            'PIPELINES_CONTAINER_NAME' => 'cloud-sea',
        );
        $env = Env::createEx($inherit);
        $env->setContainerName('dream-blue');
        $args = $env->getArgs('-e');
        $this->assertContains('PIPELINES_PARENT_CONTAINER_NAME=cloud-sea', $args);
        $this->assertContains('PIPELINES_CONTAINER_NAME=dream-blue', $args);
    }

    /**
     * @covers \Ktomk\Pipelines\Runner\Env::getInheritValue
     */
    public function testGetInheritValue()
    {
        $inherit = array(
            'DOCKER_HOST' => 'unix:///var/run/docker.sock',
            'DOCKER_TMP' => false,
        );
        $env = Env::createEx($inherit);
        $actual = $env->getInheritValue('DOCKER_HOST');
        $this->assertSame('unix:///var/run/docker.sock', $actual);
        $actual = $env->getInheritValue('DOCKER_TMP');
        $this->assertNull($actual);
        $actual = $env->getInheritValue('FOO_BAR_LE_BAZ');
        $this->assertNull($actual);
    }

    public function testGetValue()
    {
        $env = Env::createEx();
        $actual = $env->getValue('BITBUCKET_BUILD_NUMBER');
        $this->assertSame('0', $actual);
        $actual = $env->getValue('BITBUCKET_BRANCH');
        $this->assertNull($actual);
    }

    public function testSetPipelinesId()
    {
        $env = Env::createEx();
        $this->assertNull($env->getValue('PIPELINES_ID'));
        $this->assertNull($env->getValue('PIPELINES_IDS'));

        // set the first id
        $result = $env->setPipelinesId('default');
        $this->assertFalse($result);
        $this->assertSame('default', $env->getValue('PIPELINES_ID'));

        // set the second id (next run)
        $result = $env->setPipelinesId('default');
        $this->assertTrue($result);
        $actual = $env->getValue('PIPELINES_IDS');
        $this->assertNotNull($actual);
        $this->assertRegExp('~^([a-z0-9]+) \1$~', $actual, 'list of hashes');
    }

    public function testSetPipelinesProjectPath()
    {
        $env = Env::createEx();
        $env->setPipelinesProjectPath('/my-path');
        $this->assertNull($env->getValue('PIPELINES_PROJECT_PATH'), 'needs ID/s configuration');

        $env->setPipelinesId('custom/test-for-nothing');
        $env->setPipelinesProjectPath('/my-path');
        $this->assertSame('/my-path', $env->getValue('PIPELINES_PROJECT_PATH'), 'works for ID/s');

        $env->setPipelinesProjectPath('/my-path/too');
        $this->assertSame('/my-path', $env->getValue('PIPELINES_PROJECT_PATH'), 'can not overwrite');
    }

    public function testSetPipelinesProjectPathThrowsOnRelativePath()
    {
        $env = Env::createEx();

        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('not an absolute path: "./my-path"');
        $env->setPipelinesProjectPath('./my-path');
    }

    public function testInheritPipelinesId()
    {
        $inherit = array('PIPELINES_ID', 'custom/the-other-day');
        $env = Env::createEx($inherit);
        $this->assertNull($env->getValue('PIPELINES_ID'));
    }

    /**
     * @throws \Ktomk\Pipelines\Cli\ArgsException
     */
    public function testCollect()
    {
        $env = Env::createEx();
        $env->collect(new ArgsTester(), array());
        $expected = array (
            0 => 'e',
            1 => 'BITBUCKET_BUILD_NUMBER=0',
            2 => 'e',
            3 => 'BITBUCKET_COMMIT=0000000000000000000000000000000000000000',
            4 => 'e',
            5 => 'BITBUCKET_REPO_OWNER=nobody',
            6 => 'e',
            7 => 'BITBUCKET_REPO_SLUG=local-has-no-slug',
            8 => 'e',
            9 => 'BITBUCKET_STEP_RUN_NUMBER=1',
            10 => 'e',
            11 => 'CI=true',
        );
        $actual = $env->getArgs('e');
        $this->assertSame($expected, $actual);
    }

    public function testCollectFiles1()
    {
        $env = Env::createEx(array('DOCKER_ID_USER' => 'electra'));
        $env->collectFiles(array(
            __DIR__ . '/../../data/env/.env.dist',
            '/abc/xyz/nada-kar-la-da',
        ));
        $actual = $env->getResolver()->getValue('DOCKER_ID_USER');
        $this->assertSame('electra', $actual, '.dist imports');

        $actual = $env->getResolver()->getValue('FOO');
        $this->assertSame('BAR', $actual, '.dist sets');
    }

    public function testCollectFiles2()
    {
        $env = Env::createEx(array('DOCKER_ID_USER' => 'electra'));
        $env->collectFiles(array(
            '/abc/xyz/nada-kar-la-da',
            __DIR__ . '/../../data/env/.env.dist',
            __DIR__ . '/../../data/env/.env',
        ));
        $resolver = $env->getResolver();
        $actual = $resolver->getValue('DOCKER_ID_USER');
        $this->assertSame('l-oracle-de-delphi', $actual, '.env sets');

        $actual = $resolver->getValue('FOO');
        $this->assertSame('BAZ', $actual, '.env overwrites');

        $array = array(
            'first' => '$DOCKER_ID_USER',
            'second'=> '$FOO',
            'third' => '$BAZ-LE-BAZ',
        );
        $expected = array(
            'first' => 'l-oracle-de-delphi',
            'second'=> 'BAZ',
            'third' => '$BAZ-LE-BAZ',
        );
        $actual = $resolver($array);
        $this->assertSame($expected, $actual);
    }

    public function testGetResolver()
    {
        $env = new Env();
        $this->assertInstanceOf(
            'Ktomk\Pipelines\Runner\EnvResolver',
            $env->getResolver()
        );
    }

    /**
     */
    public function testAddReferenceOfUnknownType()
    {
        $this->expectException('UnexpectedValueException');
        $this->expectExceptionMessage('Unknown reference type: "foo"');

        $env = new Env();

        $reference = $this->createMock('\Ktomk\Pipelines\Runner\Reference');
        $reference
            ->method('getType')
            ->willReturn('foo');

        $env->addReference($reference);
    }

    /**
     * @covers \Ktomk\Pipelines\Runner\EnvResolver::getVariables
     *
     * @return void
     */
    public function testGetVariablesEmptyArrayOnInit()
    {
        $env = new Env();
        $this->assertSame(array(), $env->getVariables());
    }

    /**
     * @throws \Ktomk\Pipelines\Cli\ArgsException
     *
     * @return void
     */
    public function testInheritanceResolution()
    {
        $inherit = array();
        $args = new Args(array('', '-e', 'BITBUCKET_REPO_SLUG=bar'));

        // empty on create (new behaviour)
        $env = Env::create($inherit);
        $this->assertNull($env->getValue('BITBUCKET_REPO_SLUG'));

        // init default vars initializes default
        $env->initDefaultVars($env->getVariables() + $inherit);
        $this->assertSame('local-has-no-slug', $env->getValue('BITBUCKET_REPO_SLUG'));

        // collecting args works still
        $env->collect($args, array('e', 'env', 'env-file'));
        $env->initDefaultVars($env->getVariables() + $inherit);
        $this->assertSame('bar', $env->getValue('BITBUCKET_REPO_SLUG'));
    }

    public function testResetStepRunNumber()
    {
        $inherit = array();

        $env = Env::create($inherit);
        $this->assertNull($env->getValue('BITBUCKET_STEP_RUN_NUMBER'));

        $env->initDefaultVars($inherit);
        $this->assertSame('1', $env->getValue('BITBUCKET_STEP_RUN_NUMBER'));

        $inherit = array('BITBUCKET_STEP_RUN_NUMBER' => '2');

        $env = Env::create($inherit);
        $this->assertNull($env->getValue('BITBUCKET_STEP_RUN_NUMBER'));

        $env->initDefaultVars($inherit);
        $this->assertSame('2', $env->getValue('BITBUCKET_STEP_RUN_NUMBER'));

        $env->resetStepRunNumber();
        $this->assertSame('1', $env->getValue('BITBUCKET_STEP_RUN_NUMBER'));

        $env->resetStepRunNumber();
        $this->assertSame('1', $env->getValue('BITBUCKET_STEP_RUN_NUMBER'));

        $inherit = array();

        $env = Env::create($inherit);
        $this->assertNull($env->getValue('BITBUCKET_STEP_RUN_NUMBER'));

        $env->resetStepRunNumber();
        $this->assertSame('1', $env->getValue('BITBUCKET_STEP_RUN_NUMBER'));
    }
}
