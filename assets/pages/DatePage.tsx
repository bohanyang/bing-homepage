import Layout from '@/app/Layout'
import useSettingValue from '@/app/useSettingValue'
import flags from '@/images/flags'
import { Link, Head } from '@inertiajs/inertia-react'
import React from 'react'

interface Props {
  image_origin: string
  images: {
    name: string
    urlbase: string
    markets: string[]
  }[]
  formattedDate: string
  date: {
    previous: string
    current: string
    next?: string
  }
}

function DatePage ({ image_origin, images, formattedDate, date }: Props) {
  console.log(images)
  const { image_size } = useSettingValue('browseSize')
  return (
    <>
      <Head title={formattedDate} />
      <div className="row">
        <div
          className="col-xs-12"
          style={{
            paddingTop: '10px',
            paddingBottom: '10px'
          }}
        >
          <h5 className="text-primary">{formattedDate}</h5>
        </div>
      </div>
      {images.map((image) => (
        <React.Fragment key={image.name}>
          <div className="row">
            <div className="col-xs-12">
              <Link href={`/images/${image.name}`}>
                <img
                  src={`${image_origin}${image.urlbase}_${image_size}.jpg`}
                  alt={image.name}
                  className="img-rounded wallpaper"
                />
              </Link>
            </div>
          </div>
          <div className="row">
            <div
              className="col-xs-12 para-of-date"
              style={{ display: 'flex', alignItems: 'center', gap: '4px' }}
            >
              {image.markets.map((market) => (
                <a
                  href={`/${market}/${date.current}`}
                  key={market}
                >
                  <img src={flags(market)} className="flag-of-date" />
                </a>
              ))}
            </div>
          </div>
        </React.Fragment>
      ))}
      <div className="row">
        <div className="col-xs-6 pgr">
          <Link
            href={`/${date.previous}`}
            className="btn btn-embossed btn-primary btn-lg btn-block"
          >
            <i className="fui-arrow-left"></i>
          </Link>
        </div>

        <div className="col-xs-6 pgr">
          {date.next
            ? (
            <Link
              href={`/${date.next}`}
              className="btn btn-embossed btn-primary btn-lg btn-block"
            >
              <i className="fui-arrow-right"></i>
            </Link>
              )
            : (
            <button
              className="btn btn-embossed btn-default btn-lg btn-block"
              disabled
            >
              <i className="fui-arrow-right"></i>
            </button>
              )}
        </div>
      </div>
    </>
  )
}

DatePage.layout = (page: JSX.Element) => <Layout children={page} />

export default DatePage
