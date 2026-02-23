// src/components/ProductList.tsx
import { useEffect, useState } from 'react'
import { getProducts } from '../api/products'
import type { Product } from '../api/products'

export default function ProductList() {
    const [products, setProducts] = useState<Product[]>([])
    const [loading, setLoading] = useState(true)
    const [error, setError] = useState<string | null>(null)

    useEffect(() => {
        getProducts()
            .then(res => setProducts(res.items))
            .catch(err => setError(err.message))
            .finally(() => setLoading(false))
    }, [])

    if (loading) return <p>Loading...</p>
    if (error) return <p>Error: {error}</p>

    return (
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: '16px' }}>
            {products.map(p => (
                <div
                    key={p.id}
                    style={{
                        border: '1px solid #ccc',
                        padding: '16px',
                        width: '200px',
                        borderRadius: '8px',
                        textAlign: 'center',
                    }}
                >
                    <img
                        src={p.imagePath}
                        alt={p.name}
                        style={{ width: '100%', height: 'auto', marginBottom: '8px' }}
                    />
                    <h3>{p.name}</h3>
                    <p>{p.category}</p>
                    <p>${p.price.toFixed(2)}</p>
                </div>
            ))}
        </div>
    )
}