<?php

declare(strict_types=1);

namespace LaravelModulesArch\Tests\Feature\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LaravelModulesArch\Relations\NullRelation;
use LaravelModulesArch\Support\ModuleAwareRelation;
use LaravelModulesArch\Tests\TestCase;
use Mockery\MockInterface;
use RuntimeException;

class ModuleAwareRelationTest extends TestCase
{
    public function test_has_many_returns_null_relation_when_module_disabled(): void
    {
        $this->fakeModules(fn (MockInterface $m) => $m->shouldReceive('isEnabled')->with('Sale')->andReturn(false));

        $result = ModuleAwareRelation::hasMany(new ModuleAwareParent, 'Sale', ModuleAwareChild::class);

        $this->assertInstanceOf(NullRelation::class, $result);
    }

    public function test_has_many_returns_real_relation_when_module_enabled(): void
    {
        $this->fakeModules(fn (MockInterface $m) => $m->shouldReceive('isEnabled')->with('Sale')->andReturn(true));

        $result = ModuleAwareRelation::hasMany(new ModuleAwareParent, 'Sale', ModuleAwareChild::class);

        $this->assertInstanceOf(HasMany::class, $result);
    }

    public function test_has_one_falls_back_when_disabled(): void
    {
        $this->fakeModules(fn (MockInterface $m) => $m->shouldReceive('isEnabled')->with('Sale')->andReturn(false));

        $this->assertInstanceOf(
            NullRelation::class,
            ModuleAwareRelation::hasOne(new ModuleAwareParent, 'Sale', ModuleAwareChild::class),
        );
    }

    public function test_has_one_passes_through_when_enabled(): void
    {
        $this->fakeModules(fn (MockInterface $m) => $m->shouldReceive('isEnabled')->with('Sale')->andReturn(true));

        $this->assertInstanceOf(
            HasOne::class,
            ModuleAwareRelation::hasOne(new ModuleAwareParent, 'Sale', ModuleAwareChild::class),
        );
    }

    public function test_belongs_to_falls_back_when_disabled(): void
    {
        $this->fakeModules(fn (MockInterface $m) => $m->shouldReceive('isEnabled')->with('Sale')->andReturn(false));

        $this->assertInstanceOf(
            NullRelation::class,
            ModuleAwareRelation::belongsTo(new ModuleAwareChild, 'Sale', ModuleAwareParent::class),
        );
    }

    public function test_belongs_to_passes_through_when_enabled(): void
    {
        $this->fakeModules(fn (MockInterface $m) => $m->shouldReceive('isEnabled')->with('Sale')->andReturn(true));

        $this->assertInstanceOf(
            BelongsTo::class,
            ModuleAwareRelation::belongsTo(new ModuleAwareChild, 'Sale', ModuleAwareParent::class),
        );
    }

    public function test_morph_many_falls_back_when_disabled(): void
    {
        $this->fakeModules(fn (MockInterface $m) => $m->shouldReceive('isEnabled')->with('Account')->andReturn(false));

        $this->assertInstanceOf(
            NullRelation::class,
            ModuleAwareRelation::morphMany(new ModuleAwareParent, 'Account', ModuleAwareChild::class, 'morphable'),
        );
    }

    public function test_morph_many_passes_through_when_enabled(): void
    {
        $this->fakeModules(fn (MockInterface $m) => $m->shouldReceive('isEnabled')->with('Account')->andReturn(true));

        $this->assertInstanceOf(
            MorphMany::class,
            ModuleAwareRelation::morphMany(new ModuleAwareParent, 'Account', ModuleAwareChild::class, 'morphable'),
        );
    }

    public function test_morph_one_falls_back_when_disabled(): void
    {
        $this->fakeModules(fn (MockInterface $m) => $m->shouldReceive('isEnabled')->with('Account')->andReturn(false));

        $this->assertInstanceOf(
            NullRelation::class,
            ModuleAwareRelation::morphOne(new ModuleAwareParent, 'Account', ModuleAwareChild::class, 'morphable'),
        );
    }

    public function test_morph_one_passes_through_when_enabled(): void
    {
        $this->fakeModules(fn (MockInterface $m) => $m->shouldReceive('isEnabled')->with('Account')->andReturn(true));

        $this->assertInstanceOf(
            MorphOne::class,
            ModuleAwareRelation::morphOne(new ModuleAwareParent, 'Account', ModuleAwareChild::class, 'morphable'),
        );
    }

    public function test_morph_to_falls_back_when_disabled(): void
    {
        $this->fakeModules(fn (MockInterface $m) => $m->shouldReceive('isEnabled')->with('Account')->andReturn(false));

        $this->assertInstanceOf(
            NullRelation::class,
            ModuleAwareRelation::morphTo(new ModuleAwareChild, 'Account', 'morphable'),
        );
    }

    public function test_morph_to_passes_through_when_enabled(): void
    {
        $this->fakeModules(fn (MockInterface $m) => $m->shouldReceive('isEnabled')->with('Account')->andReturn(true));

        $this->assertInstanceOf(
            MorphTo::class,
            ModuleAwareRelation::morphTo(new ModuleAwareChild, 'Account', 'morphable'),
        );
    }

    public function test_unknown_module_is_treated_as_disabled(): void
    {
        $this->fakeModules(fn (MockInterface $m) => $m->shouldReceive('isEnabled')
            ->with('Ghost')
            ->andThrow(new RuntimeException('Module [Ghost] does not exist.')));

        $this->assertInstanceOf(
            NullRelation::class,
            ModuleAwareRelation::hasMany(new ModuleAwareParent, 'Ghost', ModuleAwareChild::class),
        );
    }
}

/**
 * @internal Test fixture parent model.
 */
class ModuleAwareParent extends Model
{
    protected $table = 'module_aware_parents';

    public $timestamps = false;

    protected $guarded = [];
}

/**
 * @internal Test fixture child model.
 */
class ModuleAwareChild extends Model
{
    protected $table = 'module_aware_children';

    public $timestamps = false;

    protected $guarded = [];
}
