<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    // Proses Checkout
    public function checkout(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|integer|min:1'
        ]);

        DB::beginTransaction();
        try {
            $totalPrice = 0;
            $orderItems = [];

            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);

                if (!$product || $product->stock < $item['qty']) {
                    continue;
                }

                $product->decrement('stock', $item['qty']);

                $subtotal = $product->price * $item['qty'];
                $totalPrice += $subtotal;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'qty' => $item['qty'],
                    'price' => $product->price
                ];
            }

            if (count($orderItems) === 0) {
                DB::rollBack();
                return response()->json([
                    'error' => 'Checkout gagal. Semua produk tidak tersedia atau stok tidak mencukupi.'
                ], 400);
            }

            $order = Order::create([
                'user_id' => auth()->id(),
                'total_price' => $totalPrice,
                'status' => 'Pending'
            ]);

            $details = [];

            foreach ($orderItems as $item) {
                OrderDetail::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'qty' => $item['qty'],
                    'price' => $item['price']
                ]);

                $details[] = [
                    'product_id' => $item['product_id'],
                    'product_name' => Product::find($item['product_id'])->name,
                    'qty' => $item['qty'],
                    'price' => $item['price']
                ];
            }

            DB::commit();

            return response()->json([
                'message' => 'Order berhasil dibuat',
                'order' => [
                    'id' => $order->id,
                    'total_price' => $order->total_price,
                    'status' => $order->status,
                    'user' => [
                        'id' => auth()->user()->id,
                        'name' => auth()->user()->name,
                        'email' => auth()->user()->email
                    ],
                    'items' => $details
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Gagal checkout', 'message' => $e->getMessage()], 500);
        }
    }

    public function show_by_user($user_id)
    {
        $orders = Order::with(['user', 'details.product'])
            ->where('user_id', $user_id)
            ->orderBy('created_at', 'desc')
            ->get();

        if ($orders->isEmpty()) {
            return response()->json(['message' => 'Belum ada pesanan untuk user ini.'], 200);
        }

        return response()->json($orders);
    }



    // Laporan Order (Admin only)
    public function index()
    {
        $orders = Order::with(['user', 'details.product'])->orderBy('created_at', 'desc')->get();
        return response()->json($orders);
    }
    public function show($id)
    {
        $order = Order::with(['user', 'details.product'])->find($id);

        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }
        return response()->json($order);
    }
    public function update(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,processed,completed,cancelled',
        ]);

        $order = Order::find($id);

        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        $order->status = $request->status;
        $order->save();

        return response()->json([
            'message' => 'Status pesanan berhasil diperbarui',
            'order' => $order
        ]);
    }
}
