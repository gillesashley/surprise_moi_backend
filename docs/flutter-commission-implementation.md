# Flutter Implementation Guide: Platform Commission System

## Overview

The Surprise Moi platform automatically deducts commissions from vendor orders when customers make payments. This guide shows how to integrate commission tracking and admin dashboard features into the Flutter app.

## Commission Rates by Vendor Tier

| Vendor Tier | Commission Rate | Description                                                  |
| ----------- | --------------- | ------------------------------------------------------------ |
| **Tier 1**  | **12%**         | Registered Business - Higher rate for established businesses |
| **Tier 2**  | **8%**          | Individual Vendor - Lower rate for independent sellers       |

---

## 1. Commission Data in Order Objects

When you fetch order details, the response includes commission information (if the order has been paid):

### Order Response Structure

```json
{
    "id": 18,
    "order_number": "VND-WAPZ-FIZU-260204-02",
    "user_id": 1,
    "vendor_id": 21,
    "subtotal": 360.0,
    "discount_amount": 0.0,
    "delivery_fee": 0.0,
    "total": 360.0,
    "currency": "USD",
    "status": "pending",
    "payment_status": "paid",

    // ❗ COMMISSION FIELDS - Only populated after payment
    "platform_commission_rate": 12.0,
    "platform_commission_amount": 43.2,
    "vendor_payout_amount": 316.8,

    "created_at": "2026-02-09T10:37:48+00:00",
    "updated_at": "2026-02-09T10:40:15+00:00"
}
```

### Dart Model Example

```dart
class Order {
  final int id;
  final String orderNumber;
  final double total;
  final String currency;
  final String paymentStatus;

  // Commission fields - can be null if payment not completed
  final double? platformCommissionRate;
  final double? platformCommissionAmount;
  final double? vendorPayoutAmount;

  Order({
    required this.id,
    required this.orderNumber,
    required this.total,
    required this.currency,
    required this.paymentStatus,
    this.platformCommissionRate,
    this.platformCommissionAmount,
    this.vendorPayoutAmount,
  });

  factory Order.fromJson(Map<String, dynamic> json) {
    return Order(
      id: json['id'],
      orderNumber: json['order_number'],
      total: double.parse(json['total'].toString()),
      currency: json['currency'],
      paymentStatus: json['payment_status'],
      platformCommissionRate: json['platform_commission_rate'] != null
          ? double.parse(json['platform_commission_rate'].toString())
          : null,
      platformCommissionAmount: json['platform_commission_amount'] != null
          ? double.parse(json['platform_commission_amount'].toString())
          : null,
      vendorPayoutAmount: json['vendor_payout_amount'] != null
          ? double.parse(json['vendor_payout_amount'].toString())
          : null,
    );
  }

  // Helper properties
  bool get hasCommissionData => platformCommissionAmount != null;

  String get formattedCommission {
    if (platformCommissionAmount == null) return 'N/A';
    return '$currency \$${platformCommissionAmount!.toStringAsFixed(2)}';
  }

  String get formattedVendorPayout {
    if (vendorPayoutAmount == null) return 'N/A';
    return '$currency \$${vendorPayoutAmount!.toStringAsFixed(2)}';
  }
}
```

---

## 2. Displaying Commission Info (Vendor View)

Vendors should see how much they'll receive after commission deduction:

### Vendor Order Details Widget

```dart
class VendorOrderSummary extends StatelessWidget {
  final Order order;

  const VendorOrderSummary({Key? key, required this.order}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Order ${order.orderNumber}',
                style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 16),

            // Order Total
            _buildRow('Order Total', '\$${order.total.toStringAsFixed(2)}'),

            // Show commission breakdown if paid
            if (order.paymentStatus == 'paid' && order.hasCommissionData) ...[
              const Divider(height: 24),
              _buildRow(
                'Platform Commission (${order.platformCommissionRate!.toStringAsFixed(0)}%)',
                '- \$${order.platformCommissionAmount!.toStringAsFixed(2)}',
                isNegative: true,
              ),
              const Divider(height: 24),
              _buildRow(
                'You Receive',
                '\$${order.vendorPayoutAmount!.toStringAsFixed(2)}',
                isBold: true,
                isPositive: true,
              ),
            ] else ...[
              const SizedBox(height: 8),
              Text(
                'Commission will be calculated after payment',
                style: TextStyle(color: Colors.grey[600], fontSize: 12),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildRow(String label, String value, {
    bool isNegative = false,
    bool isPositive = false,
    bool isBold = false,
  }) {
    Color? color;
    if (isNegative) color = Colors.red[700];
    if (isPositive) color = Colors.green[700];

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4.0),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: TextStyle(
              fontWeight: isBold ? FontWeight.bold : FontWeight.normal,
            ),
          ),
          Text(
            value,
            style: TextStyle(
              fontSize: isBold ? 18 : 14,
              fontWeight: isBold ? FontWeight.bold : FontWeight.normal,
              color: color,
            ),
          ),
        ],
      ),
    );
  }
}
```

---

## 3. Admin Dashboard - Commission Statistics

### 3.1 Fetch Commission Statistics

**Endpoint:** `GET /api/v1/admin/commission/statistics`

**Query Parameters:**

- `period` (optional): `all`, `today`, `week`, `month`, `year`, `custom`
- `start_date` (optional): For custom period (YYYY-MM-DD)
- `end_date` (optional): For custom period (YYYY-MM-DD)

**Headers:**

```
Authorization: Bearer {admin_token}
Accept: application/json
```

### Statistics Response Structure

```json
{
    "data": {
        "summary": {
            "total_orders": 4,
            "total_order_value": 560.0,
            "total_commission_earned": 67.2,
            "average_commission_rate": 12.0,
            "total_vendor_payouts": 492.8
        },
        "tier_breakdown": [
            {
                "tier": 1,
                "tier_name": "Tier 1 (Registered Business)",
                "order_count": 4,
                "commission_earned": 67.2,
                "average_commission_rate": 12.0
            }
        ],
        "top_vendors": [
            {
                "vendor_id": 21,
                "vendor_name": "Premium Gifts Ghana",
                "vendor_tier": 1,
                "order_count": 1,
                "total_sales": 360.0,
                "commission_generated": 43.2
            }
        ],
        "period": "all",
        "date_range": {
            "start": null,
            "end": null
        }
    }
}
```

### Dart Service

```dart
class CommissionService {
  final String baseUrl;
  final String token;

  CommissionService({required this.baseUrl, required this.token});

  Future<CommissionStatistics> getStatistics({String period = 'all'}) async {
    final response = await http.get(
      Uri.parse('$baseUrl/api/v1/admin/commission/statistics?period=$period'),
      headers: {
        'Authorization': 'Bearer $token',
        'Accept': 'application/json',
      },
    );

    if (response.statusCode == 200) {
      final data = json.decode(response.body);
      return CommissionStatistics.fromJson(data['data']);
    } else if (response.statusCode == 403) {
      throw Exception('Unauthorized. Admin access required.');
    } else {
      throw Exception('Failed to load commission statistics');
    }
  }
}

class CommissionStatistics {
  final CommissionSummary summary;
  final List<TierBreakdown> tierBreakdown;
  final List<TopVendor> topVendors;
  final String period;

  CommissionStatistics({
    required this.summary,
    required this.tierBreakdown,
    required this.topVendors,
    required this.period,
  });

  factory CommissionStatistics.fromJson(Map<String, dynamic> json) {
    return CommissionStatistics(
      summary: CommissionSummary.fromJson(json['summary']),
      tierBreakdown: (json['tier_breakdown'] as List)
          .map((e) => TierBreakdown.fromJson(e))
          .toList(),
      topVendors: (json['top_vendors'] as List)
          .map((e) => TopVendor.fromJson(e))
          .toList(),
      period: json['period'],
    );
  }
}

class CommissionSummary {
  final int totalOrders;
  final double totalOrderValue;
  final double totalCommissionEarned;
  final double averageCommissionRate;
  final double totalVendorPayouts;

  CommissionSummary({
    required this.totalOrders,
    required this.totalOrderValue,
    required this.totalCommissionEarned,
    required this.averageCommissionRate,
    required this.totalVendorPayouts,
  });

  factory CommissionSummary.fromJson(Map<String, dynamic> json) {
    return CommissionSummary(
      totalOrders: json['total_orders'],
      totalOrderValue: double.parse(json['total_order_value'].toString()),
      totalCommissionEarned: double.parse(json['total_commission_earned'].toString()),
      averageCommissionRate: double.parse(json['average_commission_rate'].toString()),
      totalVendorPayouts: double.parse(json['total_vendor_payouts'].toString()),
    );
  }
}

class TierBreakdown {
  final int tier;
  final String tierName;
  final int orderCount;
  final double commissionEarned;
  final double averageCommissionRate;

  TierBreakdown({
    required this.tier,
    required this.tierName,
    required this.orderCount,
    required this.commissionEarned,
    required this.averageCommissionRate,
  });

  factory TierBreakdown.fromJson(Map<String, dynamic> json) {
    return TierBreakdown(
      tier: json['tier'],
      tierName: json['tier_name'],
      orderCount: json['order_count'],
      commissionEarned: double.parse(json['commission_earned'].toString()),
      averageCommissionRate: double.parse(json['average_commission_rate'].toString()),
    );
  }
}

class TopVendor {
  final int vendorId;
  final String vendorName;
  final int vendorTier;
  final int orderCount;
  final double totalSales;
  final double commissionGenerated;

  TopVendor({
    required this.vendorId,
    required this.vendorName,
    required this.vendorTier,
    required this.orderCount,
    required this.totalSales,
    required this.commissionGenerated,
  });

  factory TopVendor.fromJson(Map<String, dynamic> json) {
    return TopVendor(
      vendorId: json['vendor_id'],
      vendorName: json['vendor_name'],
      vendorTier: json['vendor_tier'],
      orderCount: json['order_count'],
      totalSales: double.parse(json['total_sales'].toString()),
      commissionGenerated: double.parse(json['commission_generated'].toString()),
    );
  }
}
```

---

## 4. Admin Dashboard UI

### Commission Overview Screen

```dart
class AdminCommissionDashboard extends StatefulWidget {
  @override
  _AdminCommissionDashboardState createState() => _AdminCommissionDashboardState();
}

class _AdminCommissionDashboardState extends State<AdminCommissionDashboard> {
  late Future<CommissionStatistics> _statsFuture;
  String _selectedPeriod = 'all';

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  void _loadData() {
    setState(() {
      _statsFuture = CommissionService(
        baseUrl: 'http://your-api-url',
        token: 'your-admin-token',
      ).getStatistics(period: _selectedPeriod);
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Commission Dashboard'),
        actions: [
          // Period selector
          PopupMenuButton<String>(
            initialValue: _selectedPeriod,
            onSelected: (value) {
              setState(() => _selectedPeriod = value);
              _loadData();
            },
            itemBuilder: (context) => [
              const PopupMenuItem(value: 'today', child: Text('Today')),
              const PopupMenuItem(value: 'week', child: Text('This Week')),
              const PopupMenuItem(value: 'month', child: Text('This Month')),
              const PopupMenuItem(value: 'year', child: Text('This Year')),
              const PopupMenuItem(value: 'all', child: Text('All Time')),
            ],
            child: Padding(
              padding: const EdgeInsets.all(16.0),
              child: Row(
                children: [
                  Text(_selectedPeriod.toUpperCase()),
                  const Icon(Icons.arrow_drop_down),
                ],
              ),
            ),
          ),
        ],
      ),
      body: FutureBuilder<CommissionStatistics>(
        future: _statsFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }

          if (snapshot.hasError) {
            return Center(child: Text('Error: ${snapshot.error}'));
          }

          final stats = snapshot.data!;
          return RefreshIndicator(
            onRefresh: () async => _loadData(),
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Summary Cards
                  _buildSummaryCards(stats.summary),
                  const SizedBox(height: 24),

                  // Tier Breakdown
                  Text('Commission by Vendor Tier',
                      style: Theme.of(context).textTheme.titleLarge),
                  const SizedBox(height: 8),
                  ...stats.tierBreakdown.map((tier) => _buildTierCard(tier)),
                  const SizedBox(height: 24),

                  // Top Vendors
                  Text('Top Vendors',
                      style: Theme.of(context).textTheme.titleLarge),
                  const SizedBox(height: 8),
                  ...stats.topVendors.map((vendor) => _buildVendorCard(vendor)),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildSummaryCards(CommissionSummary summary) {
    return Column(
      children: [
        Row(
          children: [
            Expanded(
              child: _buildStatCard(
                'Total Commission',
                '\$${summary.totalCommissionEarned.toStringAsFixed(2)}',
                Icons.account_balance_wallet,
                Colors.green,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _buildStatCard(
                'Total Orders',
                summary.totalOrders.toString(),
                Icons.shopping_bag,
                Colors.blue,
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: _buildStatCard(
                'Total Sales',
                '\$${summary.totalOrderValue.toStringAsFixed(2)}',
                Icons.trending_up,
                Colors.orange,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _buildStatCard(
                'Vendor Payouts',
                '\$${summary.totalVendorPayouts.toStringAsFixed(2)}',
                Icons.payments,
                Colors.purple,
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _buildStatCard(String label, String value, IconData icon, Color color) {
    return Card(
      elevation: 2,
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(icon, color: color, size: 20),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    label,
                    style: TextStyle(
                      fontSize: 12,
                      color: Colors.grey[600],
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              value,
              style: const TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.bold,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildTierCard(TierBreakdown tier) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        title: Text(tier.tierName),
        subtitle: Text('${tier.orderCount} orders • ${tier.averageCommissionRate.toStringAsFixed(0)}% rate'),
        trailing: Text(
          '\$${tier.commissionEarned.toStringAsFixed(2)}',
          style: const TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.bold,
            color: Colors.green,
          ),
        ),
      ),
    );
  }

  Widget _buildVendorCard(TopVendor vendor) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: CircleAvatar(
          child: Text(vendor.vendorName[0].toUpperCase()),
        ),
        title: Text(vendor.vendorName),
        subtitle: Text(
          '${vendor.orderCount} orders • \$${vendor.totalSales.toStringAsFixed(2)} sales',
        ),
        trailing: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Text(
              '\$${vendor.commissionGenerated.toStringAsFixed(2)}',
              style: const TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.bold,
                color: Colors.green,
              ),
            ),
            Text(
              'Tier ${vendor.vendorTier}',
              style: TextStyle(fontSize: 10, color: Colors.grey[600]),
            ),
          ],
        ),
      ),
    );
  }
}
```

---

## 5. Admin Dashboard - Monthly Trends

### 5.1 Fetch Monthly Trend Data

**Endpoint:** `GET /api/v1/admin/commission/monthly-trend`

**Query Parameters:**

- `months` (optional): Number of months to retrieve (default: 12)

### Monthly Trend Response

```json
{
    "data": {
        "trend_data": [
            {
                "month": "2026-02",
                "month_label": "Feb 2026",
                "order_count": 6,
                "total_sales": 760.0,
                "commission_earned": 87.2,
                "average_order_value": 126.67
            }
        ],
        "summary": {
            "total_months": 1,
            "total_commission": 87.2,
            "total_orders": 6,
            "average_monthly_commission": 87.2
        }
    }
}
```

### Dart Models

```dart
class MonthlyTrendData {
  final String month;
  final String monthLabel;
  final int orderCount;
  final double totalSales;
  final double commissionEarned;
  final double averageOrderValue;

  MonthlyTrendData({
    required this.month,
    required this.monthLabel,
    required this.orderCount,
    required this.totalSales,
    required this.commissionEarned,
    required this.averageOrderValue,
  });

  factory MonthlyTrendData.fromJson(Map<String, dynamic> json) {
    return MonthlyTrendData(
      month: json['month'],
      monthLabel: json['month_label'],
      orderCount: json['order_count'],
      totalSales: double.parse(json['total_sales'].toString()),
      commissionEarned: double.parse(json['commission_earned'].toString()),
      averageOrderValue: double.parse(json['average_order_value'].toString()),
    );
  }
}

class MonthlyTrendResponse {
  final List<MonthlyTrendData> trendData;
  final TrendSummary summary;

  MonthlyTrendResponse({
    required this.trendData,
    required this.summary,
  });

  factory MonthlyTrendResponse.fromJson(Map<String, dynamic> json) {
    return MonthlyTrendResponse(
      trendData: (json['trend_data'] as List)
          .map((e) => MonthlyTrendData.fromJson(e))
          .toList(),
      summary: TrendSummary.fromJson(json['summary']),
    );
  }
}

class TrendSummary {
  final int totalMonths;
  final double totalCommission;
  final int totalOrders;
  final double averageMonthlyCommission;

  TrendSummary({
    required this.totalMonths,
    required this.totalCommission,
    required this.totalOrders,
    required this.averageMonthlyCommission,
  });

  factory TrendSummary.fromJson(Map<String, dynamic> json) {
    return TrendSummary(
      totalMonths: json['total_months'],
      totalCommission: double.parse(json['total_commission'].toString()),
      totalOrders: json['total_orders'],
      averageMonthlyCommission: double.parse(json['average_monthly_commission'].toString()),
    );
  }
}
```

### Chart Widget (using fl_chart package)

```dart
import 'package:fl_chart/fl_chart.dart';

class CommissionTrendChart extends StatelessWidget {
  final List<MonthlyTrendData> data;

  const CommissionTrendChart({Key? key, required this.data}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Commission Trend (Last ${data.length} Months)',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 16),
            SizedBox(
              height: 200,
              child: LineChart(
                LineChartData(
                  gridData: FlGridData(show: true),
                  titlesData: FlTitlesData(
                    bottomTitles: AxisTitles(
                      sideTitles: SideTitles(
                        showTitles: true,
                        getTitlesWidget: (value, meta) {
                          if (value.toInt() >= 0 && value.toInt() < data.length) {
                            return Text(
                              data[value.toInt()].monthLabel.split(' ')[0],
                              style: const TextStyle(fontSize: 10),
                            );
                          }
                          return const Text('');
                        },
                        interval: 1,
                      ),
                    ),
                    leftTitles: AxisTitles(
                      sideTitles: SideTitles(
                        showTitles: true,
                        getTitlesWidget: (value, meta) {
                          return Text('\$${value.toInt()}');
                        },
                      ),
                    ),
                    rightTitles: AxisTitles(sideTitles: SideTitles(showTitles: false)),
                    topTitles: AxisTitles(sideTitles: SideTitles(showTitles: false)),
                  ),
                  borderData: FlBorderData(show: true),
                  lineBarsData: [
                    LineChartBarData(
                      spots: data.asMap().entries.map((entry) {
                        return FlSpot(
                          entry.key.toDouble(),
                          entry.value.commissionEarned,
                        );
                      }).toList(),
                      isCurved: true,
                      color: Colors.green,
                      barWidth: 3,
                      dotData: FlDotData(show: true),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
```

---

## 6. Important User Experience Guidelines

### For Vendors:

1. ✅ **DO** show commission breakdown ONLY after payment is completed
2. ✅ **DO** clearly display the amount they'll receive (vendor_payout_amount)
3. ✅ **DO** show commission rate based on their tier
4. ❌ **DON'T** show commission fields before payment (they'll be null)

### For Customers:

1. ❌ **DON'T** show commission information to customers
2. ✅ **DO** only show the total order amount they need to pay

### For Admins:

1. ✅ **DO** provide period filters (today, week, month, year, all, custom)
2. ✅ **DO** show tier breakdown to understand commission by vendor category
3. ✅ **DO** highlight top-performing vendors
4. ✅ **DO** provide monthly trend charts for revenue forecasting

---

## 7. Testing Checklist

- [ ] Vendor sees commission breakdown after payment
- [ ] Vendor does NOT see commission data before payment
- [ ] Customer does NOT see any commission information
- [ ] Admin can filter commission stats by period
- [ ] Admin sees correct tier breakdown
- [ ] Admin sees top 10 vendors by commission generated
- [ ] Monthly trend chart displays correctly
- [ ] Commission calculations are accurate (Tier 1 = 12%, Tier 2 = 8%)

---

## Need Help?

If you encounter issues:

1. Check that the user has admin/super_admin role for admin endpoints
2. Verify payment_status is 'paid' for commission data to appear
3. Ensure commission fields are nullable in your models
4. Check API response status codes for errors

**Backend Support:** Contact backend team if commission calculations seem incorrect
