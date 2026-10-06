 CODE REVIEW EXERCISE (30 min)
This is a PR submitted by a junior developer. What feedback would you give?

```php
<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::all();

        $result = [];
        foreach ($orders as $order) {
            $user = User::find($order->user_id);
            $result[] = [
                'id' => $order->id,
                'user_name' => $user->name,
                'total' => $order->total,
            ];
        }

        return response()->json($result);
    }

    public function store(Request $request)
    {
        $order = new Order();
        $order->user_id = $request->user_id;
        $order->total = $request->total;
        $order->status = $request->status;
        $order->save();

        \DB::select("SELECT * FROM audit_log WHERE order_id = " . $order->id);

        return response()->json($order);
    }

    public function destroy($id)
    {
        $order = Order::find($id);
        $order->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
```

## Comments

**Review outcome: request changes before merging.** The controller is straightforward to follow, but it needs stronger validation, access control, and failure handling. These comments are based on the snippet; routes, middleware, model scopes, observers, and database constraints were not provided, so checks implemented elsewhere should be verified rather than assumed absent.

### 1. High priority: verify authorization and limit access to orders

`index()` loads every order visible to the model, `store()` accepts a caller-supplied `user_id`, and `destroy()` accepts any order ID without a visible permission check. Unless other layers enforce access, callers could see another customer's orders, create orders on their behalf, or delete them.

Please confirm authentication middleware protects these routes and add an `OrderPolicy` for listing, creation, and deletion. Scope the list to the current user's or tenant's permitted records; authorizing `viewAny` alone does not filter the query. For customer-created orders, derive ownership from `$request->user()->id`. Creating orders for another user should require explicit permission. See [Laravel authorization](https://laravel.com/docs/11.x/authorization).

### 2. High priority: validate input and protect business-controlled fields

`store()` assigns `user_id`, `total`, and `status` directly from the request without visible validation. Please introduce a `StoreOrderRequest` and persist only fields the operation permits.

For an ordinary checkout, calculate the total on the server from validated items, quantities, prices, and discounts. Set the initial status on the server and authorize later status transitions. Merely checking that a submitted total is numeric or a status is in an enum does not stop a customer choosing an incorrect price or marking an unpaid order as paid. If manually entered totals are a supported administrative workflow, validate their range and decimal precision and restrict that workflow to authorized users. Store money using an appropriate decimal column or integer minor units.

This is unsafe trust in request data, not a mass-assignment vulnerability in the code shown: the assignments are explicit, so `$fillable` would not protect them.

### 3. High priority: return 404 when deleting an unknown order

`Order::find($id)` can return `null`, making `$order->delete()` fail with a server error. Use `findOrFail()` or implicit route model binding, then authorize the resolved order before deleting it. Route binding handles missing records but does not grant permission. See [implicit model binding](https://laravel.com/docs/11.x/routing#implicit-binding).

Also confirm the business deletion rule: paid or fulfilled orders may need cancellation or retention rather than deletion. The snippet does not show whether the model already uses soft deletes, so permanent deletion cannot be assumed.

### 4. Medium priority: paginate and eliminate N+1 queries

`Order::all()` loads the entire result set into memory. `User::find()` inside the loop adds one user query per order, even when several orders share a user. In the ordinary case, listing N orders executes 1 + N queries.

Define an `Order::user()` relationship, eagerly load it with `with('user:id,name')`, and paginate the authorized query with deterministic ordering. If clients choose a page size, validate and cap it. Eager loading batches the user fetch; standard pagination also adds a count query. See [eager loading](https://laravel.com/docs/11.x/eloquent-relationships#eager-loading).

### 5. Medium priority: define behavior for missing users

`$user->name` assumes the lookup always succeeds. A missing, soft-deleted, or otherwise scoped-out user can make it fail. Confirm the foreign key and user-deletion policy, and explicitly define how historical orders should display their customer. A nullable name or stored customer snapshot may be appropriate; silently substituting a default user could conceal broken data.

### 6. Medium priority: clarify the audit query and make required writes atomic

`DB::select()` reads audit rows, but the result is unused. It does not create an audit record. Remove the query if it has no purpose. If the intention is to record order creation, insert an audit event instead. When the order and audit record must succeed together in the same database, wrap both writes in `DB::transaction()`.

Currently, a failure in the audit SELECT occurs after the order has been saved: the client can receive an error even though an order was created. That can lead to duplicate orders on retry. A transaction is useful for the required group of writes, not automatically necessary for every single `save()`.

If a SELECT is actually required, bind the parameter:

```php
DB::select('SELECT * FROM audit_log WHERE order_id = ?', [$order->id]);
```

The concatenated value here comes from the saved model, not directly from request input. I would flag the raw concatenation as a pattern to replace, without claiming a demonstrated SQL injection exploit from this snippet. Import `Illuminate\Support\Facades\DB` explicitly for clarity. See [parameter binding and transactions](https://laravel.com/docs/11.x/database).

### 7. Medium priority: make responses explicit and consistent

The list manually selects three fields, while creation serializes the entire model according to its visibility settings. Use an `OrderResource` to define the public fields consistently and avoid unintentionally exposing future model attributes. Return `201 Created` after creation. For deletion, either keep `200 OK` with the message or return `204 No Content` with an empty body; the current `200` response is valid. Add return types once the response contract is established.

### 8. Required verification

Please add feature tests covering:

- Unauthenticated requests and cross-user/tenant listing, creation, and deletion.
- Invalid input, attempted ownership changes, total manipulation, and disallowed statuses.
- Successful creation with the expected response fields and `201` status.
- Missing orders returning `404`, and denied deletion leaving the record unchanged.
- Pagination, stable ordering, and user-query counts that do not grow per order.
- The chosen behavior for missing or deleted users.
- Rollback when a required audit write fails, if audit logging is part of the operation.

This is a static review of the supplied exercise. No Order implementation was changed or executed.
