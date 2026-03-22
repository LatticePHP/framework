<?php

declare(strict_types=1);

namespace App;

use Lattice\Compiler\Attributes\Module;
use App\Http\WorkflowController;
use App\Workflows\OrderFulfillmentWorkflow;
use App\Activities\PaymentActivity;
use App\Activities\ShippingActivity;

#[Module(
    imports: [],
    providers: [
        OrderFulfillmentWorkflow::class,
        PaymentActivity::class,
        ShippingActivity::class,
    ],
    controllers: [WorkflowController::class],
    exports: [],
)]
final class AppModule {}
