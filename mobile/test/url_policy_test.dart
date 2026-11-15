import 'package:bab_shop_mobile/url_policy.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  final policy = UrlPolicy(Uri.parse('https://shop.example.com/'));

  test('allows the shop and Gate-E pages', () {
    expect(policy.allows(Uri.parse('https://shop.example.com/callback.php?payment_id=1')), isTrue);
    expect(policy.allows(Uri.parse('https://api01.gate-e.net/10117945825920001')), isTrue);
    expect(policy.allows(Uri.parse('https://gate-e.net/')), isTrue);
    expect(policy.allows(Uri.parse('about:blank')), isTrue);
  });

  test('blocks other hosts and look-alike domains', () {
    expect(policy.allows(Uri.parse('https://evil.example.org/')), isFalse);
    expect(policy.allows(Uri.parse('https://gate-e.net.evil.org/')), isFalse);
    expect(policy.allows(Uri.parse('https://notgate-e.net/')), isFalse);
  });

  test('blocks non-web schemes', () {
    expect(policy.allows(Uri.parse('intent://scan/#Intent;scheme=zxing;end')), isFalse);
    expect(policy.allows(Uri.parse('file:///etc/passwd')), isFalse);
  });
}
