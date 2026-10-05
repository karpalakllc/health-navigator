// Next resolves "server-only" at build time and errors if a client bundle imports
// it. Plain vitest has no such module, and the tests run on the server side of
// that boundary anyway, so it resolves to nothing here.
export {};
