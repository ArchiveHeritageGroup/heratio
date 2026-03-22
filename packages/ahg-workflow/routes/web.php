<?php

use AhgWorkflow\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {
    // Dashboard
    Route::get('/workflow', [WorkflowController::class, 'dashboard'])->name('workflow.dashboard');

    // My tasks
    Route::get('/workflow/my-tasks', [WorkflowController::class, 'myTasks'])->name('workflow.my-tasks');

    // Pool
    Route::get('/workflow/pool', [WorkflowController::class, 'pool'])->name('workflow.pool');

    // Task actions
    Route::get('/workflow/task/{id}', [WorkflowController::class, 'viewTask'])->name('workflow.task');
    Route::post('/workflow/task/{id}/claim', [WorkflowController::class, 'claimTask'])->name('workflow.task.claim');
    Route::post('/workflow/task/{id}/release', [WorkflowController::class, 'releaseTask'])->name('workflow.task.release');
    Route::post('/workflow/task/{id}/approve', [WorkflowController::class, 'approveTask'])->name('workflow.task.approve');
    Route::post('/workflow/task/{id}/reject', [WorkflowController::class, 'rejectTask'])->name('workflow.task.reject');

    // History
    Route::get('/workflow/history', [WorkflowController::class, 'history'])->name('workflow.history');

    // Queues
    Route::get('/workflow/queues', [WorkflowController::class, 'queues'])->name('workflow.queues');

    // Overdue
    Route::get('/workflow/overdue', [WorkflowController::class, 'overdue'])->name('workflow.overdue');

    // Publish readiness
    Route::get('/workflow/publish-readiness/{objectId}', [WorkflowController::class, 'publishReadiness'])->name('workflow.publish-readiness');

    // Admin: workflows
    Route::get('/workflow/admin', [WorkflowController::class, 'admin'])->name('workflow.admin');
    Route::match(['get', 'post'], '/workflow/admin/create', [WorkflowController::class, 'createWorkflow'])->name('workflow.admin.create');
    Route::match(['get', 'post'], '/workflow/admin/{id}/edit', [WorkflowController::class, 'editWorkflow'])->name('workflow.admin.edit');
    Route::post('/workflow/admin/{id}/delete', [WorkflowController::class, 'deleteWorkflow'])->name('workflow.admin.delete');

    // Admin: steps
    Route::post('/workflow/admin/{workflowId}/step/add', [WorkflowController::class, 'addStep'])->name('workflow.admin.step.add');
    Route::post('/workflow/admin/step/{id}/delete', [WorkflowController::class, 'deleteStep'])->name('workflow.admin.step.delete');

    // Admin: publish gates
    Route::get('/workflow/admin/gates', [WorkflowController::class, 'gateAdmin'])->name('workflow.gates.admin');
    Route::match(['get', 'post'], '/workflow/admin/gates/edit/{id?}', [WorkflowController::class, 'gateRuleEdit'])->name('workflow.gates.edit');
    Route::post('/workflow/admin/gates/{id}/delete', [WorkflowController::class, 'deleteGateRule'])->name('workflow.gates.delete');
});
    Route::match(['get','post'], '/workflow/admin/{workflowId}/step/add-form', [WorkflowController::class, 'addStepForm'])->name('workflow.admin.step.add-form');
    Route::match(['get','post'], '/workflow/admin/step/{id}/edit-form', [WorkflowController::class, 'editStepForm'])->name('workflow.admin.step.edit-form');
    Route::get('/workflow/bulk-preview', [WorkflowController::class, 'bulkPreview'])->name('workflow.bulk-preview');
    Route::get('/workflow/my-work', [WorkflowController::class, 'myWork'])->name('workflow.my-work');
    Route::get('/workflow/publish-simulate/{objectId}', [WorkflowController::class, 'publishSimulate'])->name('workflow.publish-simulate')->whereNumber('objectId');
    Route::get('/workflow/team-work', [WorkflowController::class, 'teamWork'])->name('workflow.team-work');
    Route::get('/workflow/timeline/{id}', [WorkflowController::class, 'timeline'])->name('workflow.timeline')->whereNumber('id');
