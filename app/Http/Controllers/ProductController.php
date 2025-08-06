<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Carbon\Carbon;
use App\Models\Order;
use App\Models\OrderDetail;
use Illuminate\Support\Facades\DB;


class ProductController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('search');
        $products = Product::when($search, function ($q) use ($search) {
            return $q->where('name', 'like', "%$search%");
        })->get();

        return response()->json($products);
    }

    // Tambah Produk (Admin only)
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'stock' => 'required|integer',
            'image' => 'nullable|string'
        ]);

        $product = Product::create($request->all());
        return response()->json($product, 201);
    }

    // Lihat 1 Produk
    public function show($id)
    {
        $product = Product::findOrFail($id);
        return response()->json($product);
    }

    // Update Produk
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $product->update($request->all());
        return response()->json($product);
    }

    // Hapus Produk
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();
        return response()->json(['message' => 'Product deleted']);
    }
    public function dashboardSummary()
    {
        $today = Carbon::today();

        // Total penjualan (semua status)
        $todaySales = Order::whereDate('created_at', $today)->sum('total_price');
        $totalSales = Order::sum('total_price');

        // Revenue hanya dari pesanan yang completed
        $todayRevenue = Order::whereDate('created_at', $today)
            ->where('status', 'completed')
            ->sum('total_price');
        $totalRevenue = Order::where('status', 'completed')->sum('total_price');

        // Produk terlaris (top 5 by quantity)
        $bestsellers = OrderDetail::select('product_id', DB::raw('SUM(qty) as total_qty'))
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->with('product:id,name') // Ambil nama produk saja
            ->take(5)
            ->get();

        // Ringkasan status pesanan
        $orderStatusSummary = Order::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // Penjualan mingguan (7 hari terakhir, urut Senin-Minggu)
        $weeklySalesRaw = OrderDetail::select(
            DB::raw("DAYNAME(orders.created_at) as day"),
            DB::raw("SUM(qty) as total_qty")
        )
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->whereDate('orders.created_at', '>=', now()->startOfWeek())
            ->groupBy('day')
            ->get()
            ->pluck('total_qty', 'day')
            ->toArray();

        $dayOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $weeklySales = [];

        foreach ($dayOrder as $day) {
            $weeklySales[] = (int) ($weeklySalesRaw[$day] ?? 0);
        }

        // Pertumbuhan bulanan (8 bulan terakhir)
        $monthlySales = Order::select(
            DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
            DB::raw('SUM(total_price) as total')
        )
            ->groupBy('month')
            ->orderBy('month')
            ->limit(8)
            ->get();

        return response()->json([
            'today_sales' => (int) $todaySales,
            'total_sales' => (int) $totalSales,
            'today_revenue' => (int) $todayRevenue,
            'total_revenue' => (int) $totalRevenue,
            'bestsellers' => $bestsellers,
            'order_status_summary' => $orderStatusSummary,
            'weekly_sales' => $weeklySales,
            'monthly_sales' => $monthlySales,
        ]);
    }
}
