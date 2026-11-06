const { test } = require('node:test');
const assert = require('node:assert');
const { normaliserAdresse } = require('../www/adresse.js');

test("ajoute http:// et retire la barre finale", () => {
  assert.strictEqual(normaliserAdresse('192.168.1.20:8000'), 'http://192.168.1.20:8000');
  assert.strictEqual(normaliserAdresse(' http://192.168.1.20/saint-martin-toukra/public/ '), 'http://192.168.1.20/saint-martin-toukra/public');
});

test("garde https et le chemin", () => {
  assert.strictEqual(normaliserAdresse('https://ecole.exemple.td'), 'https://ecole.exemple.td');
  assert.strictEqual(normaliserAdresse('HTTPS://ecole.exemple.td/app'), 'https://ecole.exemple.td/app');
});

test("refuse une saisie vide ou invalide", () => {
  assert.strictEqual(normaliserAdresse(''), null);
  assert.strictEqual(normaliserAdresse('http://'), null);
  assert.strictEqual(normaliserAdresse('   '), null);
});
