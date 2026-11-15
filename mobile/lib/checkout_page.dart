import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';

import 'config.dart';
import 'url_policy.dart';

const _navy = Color(0xFF0B2545);
const _accent = Color(0xFF14B8A6);

class CheckoutPage extends StatefulWidget {
  const CheckoutPage({super.key});

  @override
  State<CheckoutPage> createState() => _CheckoutPageState();
}

class _CheckoutPageState extends State<CheckoutPage> {
  late final WebViewController _controller;
  final UrlPolicy _policy = UrlPolicy(Uri.parse(checkoutUrl));

  int _progress = 0;
  bool _failed = false;

  @override
  void initState() {
    super.initState();

    _controller = WebViewController();
    if (kIsWeb) {
      // The web implementation is an iframe: it only supports loadRequest.
      _controller.loadRequest(Uri.parse(checkoutUrl));
      return;
    }

    _controller
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(const Color(0xFFF4F6FA))
      ..setNavigationDelegate(
        NavigationDelegate(
          onNavigationRequest: (request) => _policy.allows(Uri.parse(request.url))
              ? NavigationDecision.navigate
              : NavigationDecision.prevent,
          onPageStarted: (_) => setState(() => _failed = false),
          onProgress: (value) => setState(() => _progress = value),
          // Only the main page matters; a failed image or the like should not hide the shop.
          onWebResourceError: (error) {
            if (error.isForMainFrame ?? false) {
              setState(() => _failed = true);
            }
          },
        ),
      )
      ..loadRequest(Uri.parse(checkoutUrl));
  }

  Future<void> _handleBack(bool didPop, Object? result) async {
    if (didPop) {
      return;
    }
    if (!kIsWeb && await _controller.canGoBack()) {
      await _controller.goBack();
    } else if (mounted) {
      Navigator.of(context).maybePop();
    }
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: _handleBack,
      child: Scaffold(
        appBar: AppBar(
          backgroundColor: _navy,
          foregroundColor: Colors.white,
          title: const Text('BAB Shop', style: TextStyle(fontWeight: FontWeight.w700)),
          actions: [
            IconButton(
              tooltip: 'Reload',
              icon: const Icon(Icons.refresh),
              onPressed: kIsWeb ? () => _controller.loadRequest(Uri.parse(checkoutUrl)) : _controller.reload,
            ),
          ],
          bottom: PreferredSize(
            preferredSize: const Size.fromHeight(3),
            child: _progress < 100 && !_failed
                ? LinearProgressIndicator(
                    value: _progress / 100,
                    minHeight: 3,
                    color: _accent,
                    backgroundColor: Colors.transparent,
                  )
                : const SizedBox(height: 3),
          ),
        ),
        body: SafeArea(
          child: _failed
              ? _ErrorView(onRetry: _controller.reload)
              : WebViewWidget(controller: _controller),
        ),
      ),
    );
  }
}

class _ErrorView extends StatelessWidget {
  const _ErrorView({required this.onRetry});

  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.wifi_off, size: 56, color: Colors.black38),
            const SizedBox(height: 16),
            const Text(
              'Could not reach the shop',
              style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700, color: _navy),
            ),
            const SizedBox(height: 8),
            const Text(
              'Check your connection and try again.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.black54),
            ),
            const SizedBox(height: 24),
            FilledButton(
              style: FilledButton.styleFrom(backgroundColor: _navy),
              onPressed: onRetry,
              child: const Text('Try again'),
            ),
          ],
        ),
      ),
    );
  }
}
