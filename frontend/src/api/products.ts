// src/api/products.ts
export interface Product {
    id: number
    category: string
    name: string
    price: number
    imagePath: string
}

export interface ProductsResponse {
    items: Product[]
    page: number
    pageSize: number
    total: number
}

export const getProducts = async (): Promise<ProductsResponse> => {
    const res = await fetch('/api/products')
    if (!res.ok) throw new Error('Failed to fetch products')
    return res.json()
}