import 'package:flutter/material.dart';

import 'checkout_page.dart';

void main() {
  runApp(const BabShopApp());
}

class BabShopApp extends StatelessWidget {
  const BabShopApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'BAB Shop',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        useMaterial3: true,
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF0B2545)),
      ),
      home: const CheckoutPage(),
    );
  }
}
