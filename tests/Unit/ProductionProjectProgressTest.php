<?php

use App\Models\ProductionProject;
use App\Models\ProductionTask;
use App\Models\ProductionTaskItem;
use Illuminate\Support\Collection;

test('project progress is weighted by the number of inclusions in each task', function () {
    $largeTask = new ProductionTask(['progress' => 50]);
    $largeTask->setRelation('items', new Collection([
        new ProductionTaskItem(),
        new ProductionTaskItem(),
        new ProductionTaskItem(),
    ]));

    $smallTask = new ProductionTask(['progress' => 100]);
    $smallTask->setRelation('items', new Collection([
        new ProductionTaskItem(),
    ]));

    $project = new ProductionProject();
    $project->setRelation('tasks', new Collection([$largeTask, $smallTask]));

    expect($project->progress_percentage)->toBe(63);
});

test('project progress is zero before fulfillment creates tasks', function () {
    $project = new ProductionProject();
    $project->setRelation('tasks', new Collection());

    expect($project->progress_percentage)->toBe(0);
});
